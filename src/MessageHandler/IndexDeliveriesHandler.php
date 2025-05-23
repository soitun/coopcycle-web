<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Delivery;
use AppBundle\Message\IndexDeliveries;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psonic\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Twig\Environment as TwigEnvironment;

#[AsMessageHandler]
class IndexDeliveriesHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Client $ingestClient,
        private Client $controlClient,
        private TwigEnvironment $twig,
        private string $sonicSecretPassword,
        private string $namespace,
        private LoggerInterface $logger)
    {}

    public function __invoke(IndexDeliveries $message)
    {
        $ingest  = new \Psonic\Ingest($this->ingestClient);
        $control = new \Psonic\Control($this->controlClient);

        $ingest->connect($this->sonicSecretPassword);
        $control->connect($this->sonicSecretPassword);

        $allCollectionName = 'store:*:deliveries';

        $qb = $this->entityManager->getRepository(Delivery::class)
            ->createQueryBuilder('d');

        $q = $qb
            ->andWhere(
                $qb->expr()->in('d.id', $message->getIds())
            )
            ->getQuery();

        foreach ($q->toIterable() as $delivery) {

            $html = $this->twig->render('sonic/delivery.html.twig', [
                'delivery' => $delivery,
            ]);

            $response = $ingest->push($allCollectionName, $this->namespace, $delivery->getId(), $html);
            $status = $response->getStatus(); // Should be "OK"

            $this->logger->info(sprintf('[%s] %s: %s', $allCollectionName, $delivery->getId(), $status));

            $store = $delivery->getStore();

            if ($store) {
                $collectionName = sprintf('store:%d:deliveries', $store->getId());

                $response = $ingest->push($collectionName, $this->namespace, $delivery->getId(), $html);
                $status = $response->getStatus(); // Should be "OK"

                $this->logger->info(sprintf('[%s] %s: %s', $collectionName, $delivery->getId(), $status));
            }
        }

        $ingest->disconnect();
        $control->disconnect();
    }
}
