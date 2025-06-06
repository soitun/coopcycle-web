<?php

namespace AppBundle\Command;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Edifact\EDIFACTMessage;
use AppBundle\Entity\Edifact\EDIFACTMessageRepository;
use AppBundle\Entity\Store;
use AppBundle\Service\SettingsManager;
use AppBundle\Transporter\ImportFromPoint;
use AppBundle\Transporter\ReportFromCC;
use AppBundle\Transporter\TransporterHelpers;
use Carbon\Carbon;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Transporter\DTO\Mesurement;
use Transporter\DTO\Point;
use Transporter\Enum\INOVERTMessageType;
use Transporter\Enum\NameAndAddressType;
use Transporter\Enum\TransporterName;
use Transporter\Interface\TransporterSync;
use Transporter\Transporter;
use Transporter\TransporterException;
use Transporter\TransporterImpl;
use Transporter\TransporterOptions;
use Transporter\TransporterSyncOptions;

class SyncTransportersCommand extends Command {

    public $logger;
    use LockableTrait;

    private string $transporter;
    private Address $HQAddress;
    private Store $store;

    private TransporterImpl $impl;

    private ?string $companyLegalName;
    private ?string $companyLegalID;

    private bool $dryRun;
    private OutputInterface $output;

    public function __construct(
        private string $appName,
        private EntityManagerInterface $entityManager,
        private ParameterBagInterface $params,
        private SettingsManager $settingsManager,
        private LoggerInterface $transporterLogger,
        private ImportFromPoint $importFromPoint,
        private ReportFromCC $reportFromCC,
        private Filesystem $edifactFs
    )
    { parent::__construct(); }

    protected function configure(): void
    {
        $this->setName('coopcycle:transporters:sync')
        ->setDescription('Synchronizes transporters');

        $this->addArgument('transporter', InputArgument::REQUIRED, 'Transporter name');

        $this->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Dry run mode');
    }

    /**
     * @throws Exception
     */
    private function setup(TransporterName $transporter): void
    {
        $pos = explode(',', $this->settingsManager->get('latlng') ?? '');
        if (count($pos) !== 2) {
            throw new Exception('Invalid lat-lng setting');
        }

        $this->companyLegalName = $this->settingsManager->get('company_legal_name');
        if (empty($this->companyLegalName)) {
            throw new Exception('Company name not set');
        }

        $this->companyLegalID = $this->settingsManager->get('company_legal_id');
        if (empty($this->companyLegalID)) {
            throw new Exception('Company ID not set');
        }

        $this->importFromPoint->setDefaultCoordinates(new GeoCoordinates($pos[0], $pos[1]));
        $repo = $this->entityManager->getRepository(Store::class);

        /** @var ?Store $store */
        $store = $repo->findOneBy(['transporter' => $this->transporter]);
        if (is_null($store)) {
            throw new Exception(sprintf(
                'No store with transporter "%s" connected',
                $this->transporter
            ));
        }

        $address = $store->getAddress();
        if (is_null($address)) {
            throw new Exception('Store without address');
        }

        $this->impl = new TransporterImpl($transporter);

        $this->store = $store;
        $this->HQAddress = $address;

    }

    /**
     * @throws FilesystemException
     * @throws TransporterException
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $transporterName = TransporterName::from($input->getArgument('transporter'));
        $this->transporter = $transporterName->value;

        if (!$this->lock(sprintf(
            '%s_%s_%s.lock',
            $this->appName,
            $this->getName(),
            $this->transporter
        ))) {
            $output->writeln('The command is already running in another process.');
            $this->transporterLogger->warning('The command is already running in another process.',
                ['transporter' => $this->transporter]
            );
            return Command::FAILURE;
        }


        try {
            $this->setup($transporterName);
        } catch (Exception $e) {
            $this->transporterLogger->warning(
                sprintf('Failed to setup transporter %s: %s', $this->transporter, $e->getMessage()),
                ['transporter' => $this->transporter]
            );
            throw $e;
        }

        $this->dryRun = $input->getOption('dry-run');
        $this->output = $output;

        $config = $this->params->get('transporters_config');
        if (!($config[$this->transporter]['enabled'] ?? false)) {
            $this->transporterLogger->warning(
                sprintf('%s is not configured or enabled', $this->transporter),
                ['transporter' => $this->transporter]
            );
            throw new Exception(sprintf('%s is not configured or enabled', $this->transporter));
        }
        $config = $config[$this->transporter];

        if (isset($config['sync']['uri'])) {
            $inFs = $outFs = $this->initTransporterSyncOptions($config['sync']);
        }
        elseif (isset($config['sync']['in']) && isset($config['sync']['out'])) {
            $inFs = $this->initTransporterSyncOptions($config['sync']['in']);
            $outFs = $this->initTransporterSyncOptions($config['sync']['out']);
        } else {
            $this->transporterLogger->critical(
                'Sync not configured',
                ['transporter' => $this->transporter]
            );
            return Command::FAILURE;
        }

        $opts = new TransporterOptions(
            $transporterName,
            $this->companyLegalName, $this->companyLegalID,
            $config['legal_name'], $config['legal_id'],
            $outFs, $inFs
        );

        /** @var TransporterSync $sync */
        $sync = new ($this->impl->sync)($opts);

        if ($this->dryRun) {
            $output->writeln("Dry run mode, nothing will be imported");
        }

        $this->transporterLogger->info(
            sprintf(
                'Syncing %s with %s',
                $this->appName,
                $this->transporter
            ),
            ['transporter' => $this->transporter]
        );

        try {
            $this->importAllTasks($sync);
        } catch (Exception $e) {
            $this->transporterLogger->critical(
                sprintf(
                    'Failed to import tasks: %s',
                    $e->getMessage()
                ),
                ['transporter' => $this->transporter]
            );
            throw $e;
        }

        try {
            $this->sendReports($sync, $opts);
        } catch (Exception $e) {
            $this->transporterLogger->critical(
                sprintf(
                    'Failed to send reports: %s',
                    $e->getMessage()
                ),
                ['transporter' => $this->transporter]
            );
            throw $e;
        }

        $this->release();

        return Command::SUCCESS;
    }

    /**
     * @throws FilesystemException
     */
    private function sendReports(TransporterSync $sync, TransporterOptions $opts): void
    {
        /** @var EDIFACTMessageRepository $repo */
        $repo = $this->entityManager->getRepository(EDIFACTMessage::class);

        $unsynced = $repo->getUnsynced($this->transporter);
        if (count($unsynced) === 0) {
            $this->output->writeln("No messages to send");
            $this->transporterLogger->info("No messages to send", ['transporter' => $this->transporter]);
            return;
        }

        $this->output->writeln(sprintf("%s messages to send", count($unsynced)));
        $reports = array_map(
            fn(EDIFACTMessage $m) => $this->reportFromCC->generateReport($m, $opts),
            $unsynced
        );

        $content = $this->reportFromCC->buildSCONTR($reports, $opts);

        // This is the name of the file that will be stored on our S3
        $filename = sprintf(
                "REPORT-%s_%s.%s.edi", mb_strtolower($this->transporter),
                date('Y-m-d_His'), uniqid()
            );

        $this->edifactFs->write($filename, $content);
        $sync->push($content);
        $ids = array_map(fn(EDIFACTMessage $m) => $m->getId(), $unsynced);
        $repo->setSynced($ids, $filename);
        $this->entityManager->flush();
    }

    /**
     * @throws FilesystemException
     * @throws TransporterException
     * @throws Exception
     */
    private function importAllTasks(TransporterSync $sync): void
    {
        $count = 0;
        foreach ($sync->pull() as $content) {
            $messages = Transporter::parse(
                $content,
                INOVERTMessageType::SCONTR,
                TransporterName::from($this->transporter)
            );
            $filename = sprintf(
                "SCONTR-%s_%s.%s.edi", mb_strtolower($this->transporter),
                date('Y-m-d_His'), uniqid()
            );

            if (!$this->dryRun) {
                $this->edifactFs->write($filename, $content);
            }

            foreach ($messages as $tasks) {
                foreach ($tasks->getTasks() as $task) {
                    $edifactMessage = $this->storeSCONTR($task, $filename);
                    $this->importTask($task, $edifactMessage);
                    $count++;
                }
            }
        }
        $this->output->writeln("Remove files to acknowledge import");
        $this->transporterLogger->info("Remove files to acknowledge import", ['transporter' => $this->transporter]);
        $sync->flush($this->dryRun);
        $this->output->writeln("Done syncing, imported $count tasks");
        $this->transporterLogger->info("Done syncing, imported $count tasks", ['transporter' => $this->transporter]);
    }

    private function importTask(Point $point, EDIFACTMessage $edi): void {
        if ($this->output->isVerbose()) {
            $this->debugPoint($point);
        }

        // PICKUP SETUP
        $pickup = $this->importFromPoint->buildPickupTask($this->HQAddress->clone(), $edi);

        // DROPOFF SETUP
        $task = $this->importFromPoint->import($point, $edi);
        $task->setPrevious($pickup);


        // DELIVERY SETUP
        $delivery = new Delivery();
        $delivery->setTasks([$pickup, $task]);
        $delivery->setStore($this->store);

        //TODO: Change this, methods are marked as deprecated
        $delivery->setPickupRange($this->startOfDay(), $this->endOfDay());
        $delivery->setDropoffRange($this->startOfDay(), $this->endOfDay());

        if (!$this->dryRun) {
            $this->entityManager->persist($edi);
            $this->entityManager->persist($pickup);
            $this->entityManager->persist($task);
            $this->entityManager->persist($delivery);
            $this->entityManager->flush();
        }
    }

    private function startOfDay(): \DateTime {
        $carbon = new Carbon();
        return $carbon->startOfDay()->toDateTime();
    }

    private function endOfDay(): \DateTime {
        $carbon = new Carbon();
        return $carbon->endOfDay()->toDateTime();
    }

    private function storeSCONTR(Point $task, string $filename): EDIFACTMessage
    {
        $edi = new EDIFACTMessage();
        $edi->setMessageType(EDIFACTMessage::MESSAGE_TYPE_SCONTR);
        $edi->setReference($task->getId());
        $edi->setTransporter($this->transporter);
        $edi->setDirection(EDIFACTMessage::DIRECTION_INBOUND);
        $edi->setEdifactFile($filename);
        return $edi;
    }

    /**
     * @param array<string,mixed> $config
     */
    private function initTransporterSyncOptions(array $config = []): TransporterSyncOptions
    {
        // This is used for testing purposes
        if ($config['uri'] instanceof Filesystem) {
            return new TransporterSyncOptions($config['uri'], [
                'filemask' => $config['filemask'] ?? null
            ]);
        }

        try {
            $fs = TransporterHelpers::parseSyncOptions($config['uri']);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage(), ['transporter' => $this->transporter]);
            throw $e;
        }


        return new TransporterSyncOptions($fs, [
            'filemask' => $config['filemask'] ?? null
        ]);
    }

    private function debugPoint(Point $point): void {
        $this->output->writeln("Task ID: ".$point->getId()."\n");
        $this->output->writeln("Recipient address: ".$point->getNamesAndAddresses(NameAndAddressType::RECIPIENT)[0]->getAddress());
        $this->output->write("Times: ");
        $this->output->writeln(collect($point->getDates())->map(fn($date) => $date->getEvent()->name . ' -> ' . $date->getDate()->format('d/m/Y'))->join("\n"));
        $this->output->writeln("Number of packages: " . count($point->getPackages()));
        $this->output->writeln("Total weight: " . array_sum(array_map(fn(Mesurement $p) => $p->getQuantity(), $point->getMesurements()))." kg");
        $this->output->writeln("Comments: ".$point->getComments());
        $this->output->writeln("");
    }
}
