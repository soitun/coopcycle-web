<?php

namespace AppBundle\Controller\Utils;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Entity\Address;
use AppBundle\Entity\Hub;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\User;
use AppBundle\Entity\Store;
use AppBundle\Entity\Tag;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskImage;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Entity\Task\RecurrenceRule as TaskRecurrenceRule;
use AppBundle\Entity\Tour;
use AppBundle\Form\TaskExportType;
use AppBundle\Form\TaskGroupType;
use AppBundle\Form\TaskUploadType;
use AppBundle\Service\TagManager;
use AppBundle\Service\TaskListManager;
use AppBundle\Service\TaskManager;
use AppBundle\Utils\TaskImageNamer;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\Query\Expr;
use Nucleos\UserBundle\Model\UserInterface;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Hashids\Hashids;
use League\Flysystem\Filesystem;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use phpcent\Client as CentrifugoClient;
use Psr\Log\LoggerInterface;
use Redis;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\MimeType\FileinfoMimeTypeGuesser;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Vich\UploaderBundle\Storage\StorageInterface;

trait AdminDashboardTrait
{
    protected function redirectToDashboard(Request $request, array $params = [])
    {
        $nav = $request->query->getBoolean('nav', true);

        $defaultParams = [
            'date' => $request->get('date', (new \DateTime())->format('Y-m-d')),
        ];

        if (!$nav) {
            $defaultParams['nav'] = 'off';
        }

        return $this->redirectToRoute('admin_dashboard_fullscreen', array_merge($defaultParams, $params));
    }

    #[Route("/admin/dashboard", name: "admin_dashboard")]
    public function dashboardAction(Request $request,
        TaskManager $taskManager,
        JWTTokenManagerInterface $jwtManager,
        CentrifugoClient $centrifugoClient,
        Redis $tile38,
        IriConverterInterface $iriConverter,
        TagManager $tagManager,
        NormalizerInterface $normalizer)
    {
        return $this->dashboardFullscreenAction((new \DateTime())->format('Y-m-d'),
            $request, $taskManager, $jwtManager, $centrifugoClient, $tile38, $iriConverter, $tagManager, $normalizer);
    }

    #[Route("/admin/dashboard/fullscreen/{date}", name: "admin_dashboard_fullscreen", requirements: ["date" => "[0-9]{4}-[0-9]{2}-[0-9]{2}"])]
    public function dashboardFullscreenAction($date, Request $request,
        TaskManager $taskManager,
        JWTTokenManagerInterface $jwtManager,
        CentrifugoClient $centrifugoClient,
        Redis $tile38,
        IriConverterInterface $iriConverter,
        TagManager $tagManager,
        NormalizerInterface $normalizer)
    {
        new Hashids($this->getParameter('secret'), 8);

        $date = new \DateTime($date);

        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }

        $taskExport = new \stdClass();
        $taskExport->start = new \DateTime('first day of this month');
        $taskExport->end = new \DateTime();

        $taskExportForm = $this->createForm(TaskExportType::class, $taskExport);

        $taskExportForm->handleRequest($request);
        if ($taskExportForm->isSubmitted() && $taskExportForm->isValid()) {

            $taskExport = $taskExportForm->getData();
            $filename = sprintf('tasks-%s.csv', $date->format('Y-m-d'));

            $response = new Response($taskExport->csv);

            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $filename
            ));

            return $response;
        }

        $couriers = $this->entityManager
            ->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('u.username')
            ->where('u.roles LIKE :roles')
            ->orderBy('u.username', 'ASC')
            ->setParameter('roles', '%ROLE_COURIER%')
            ->getQuery()
            ->getArrayResult();

        $this->entityManager->getFilters()->enable('soft_deleteable');
        // insert here all queries for soft deletable that you don't want to show in the dashboard

        $recurrenceRules =
            $this->entityManager->getRepository(TaskRecurrenceRule::class)->findByGenerateOrders(false);

        $recurrenceRulesNormalized = array_map(function (TaskRecurrenceRule $recurrenceRule) use ($normalizer) {
            return $normalizer->normalize($recurrenceRule, 'jsonld');
        }, $recurrenceRules);

        $stores = $this->entityManager->getRepository(Store::class)->findBy([], ['name' => 'ASC']);

        $this->entityManager->getFilters()->disable('soft_deleteable');

        $storesNormalized = array_map(function (Store $store) use ($normalizer) {
            return $normalizer->normalize($store, 'jsonld', [
                'groups' => ['store', 'store_with_packages']
            ]);
        }, $stores);

        $qb = $this->entityManager
            ->getRepository(Address::class)
            ->createQueryBuilder('a');
        $qb
            ->select('a.id')
            ->leftJoin(LocalBusiness::class, 'r', Expr\Join::WITH, 'r.address = a.id')
            ->leftJoin(Hub::class,           'h', Expr\Join::WITH, 'h.address = a.id')
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->isNotNull('r.id'),
                    $qb->expr()->isNotNull('h.id')
                )
            );

        $addressIris = array_map(
            fn ($address) => $iriConverter->getIriFromResource(Address::class, context: ['uri_variables' => $address]),
            $qb->getQuery()->getArrayResult()
        );

        return $this->render('admin/dashboard_iframe.html.twig', $this->auth([
            'nav' => $request->query->getBoolean('nav', true),
            'date' => $date,
            'couriers' => $couriers,
            'task_export_form' => $taskExportForm->createView(),
            'tags' => $tagManager->getAllTags(),
            'jwt' => $jwtManager->create($this->getUser()),
            'centrifugo_token' => $centrifugoClient->generateConnectionToken($this->getUser()->getUsername(), (time() + 3600)),
            'centrifugo_tracking_channel' => sprintf('$%s_tracking', $this->getParameter('centrifugo_namespace')),
            'centrifugo_events_channel' => sprintf('%s_events#%s', $this->getParameter('centrifugo_namespace'), $this->getUser()->getUsername()),
            'positions' => $this->loadPositions($tile38),
            'task_recurrence_rules' => $recurrenceRulesNormalized,
            'stores' => $storesNormalized,
            'pickup_cluster_addresses' => $addressIris,
            'export_enabled' => $this->isGranted('ROLE_ADMIN') ? 'on' : 'off',
        ]));
    }

    private function loadPositions(Redis $tile38, $cursor = 0, array $points = [])
    {
        $result = $tile38->rawCommand(
            'SCAN',
            $this->getParameter('tile38_fleet_key'),
            'CURSOR',
            $cursor,
            'LIMIT',
            '10'
        );

        $newCursor = $result[0];
        $objects = $result[1];

        // Remember: more or less than COUNT or no keys may be returned
        // See http://redis.io/commands/scan#the-count-option
        // Also, SCAN may return the same key multiple times
        // See http://redis.io/commands/scan#scan-guarantees
        // Additionally, you should always have the code that uses the keys
        // before the code checking the cursor.
        if (count($objects) > 0) {
            foreach ($objects as $object) {
                [$username, $data] = $object;
                $point = json_decode($data, true);
                // Warning: format is lng,lat
                [$longitude, $latitude, $timestamp] = $point['coordinates'];

                $points[] = [
                    'username' => $username,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'timestamp' => $timestamp,
                ];
            }
        }

        // It's important to note that the cursor and returned keys
        // vary independently. The scan is never complete until redis
        // returns a non-zero cursor. However, with MATCH and large
        // collections, most iterations will return an empty keys array.

        // Still, a cursor of zero DOES NOT mean that there are no keys.
        // A zero cursor just means that the SCAN is complete, but there
        // might be one last batch of results to process.

        // From <http://redis.io/commands/scan>:
        // 'An iteration starts when the cursor is set to 0,
        // and terminates when the cursor returned by the server is 0.'
        if ($newCursor === 0) {
            return $points;
        }

        return $this->loadPositions($tile38, $newCursor, $points);
    }

    protected function getTaskList(\DateTime $date, UserInterface $user)
    {
        $this->denyAccessUnlessGranted('ROLE_DISPATCHER');
        $taskList = $this->entityManager
            ->getRepository(TaskList::class)
            ->findOneBy(['date' => $date, 'courier' => $user]);

        if (null === $taskList) {
            $taskList = new TaskList();
            $taskList->setDate($date);
            $taskList->setCourier($user);
        }

        return $taskList;
    }

    #[Route("/admin/task-lists/{date}/{username}", name: "admin_task_list_create", requirements: ["date" => "[0-9]{4}-[0-9]{2}-[0-9]{2}"], methods: ["POST"])]
    public function createTaskListAction($date, $username, Request $request, UserManagerInterface $userManager, NormalizerInterface $normalizer)
    {
        $this->denyAccessUnlessGranted('ROLE_DISPATCHER');

        $date = new \DateTime($date);
        $user = $userManager->findUserByUsername($username);

        $taskList = $this->getTaskList($date, $user);

        if (null === $taskList->getId()) {
            $this->entityManager->persist($taskList);
            $this->entityManager->flush();
        }

        $taskListNormalized = $normalizer->normalize($taskList, 'jsonld', [
            'groups' => ['task_list']
        ]);

        return new JsonResponse($taskListNormalized);
    }

    #[Route("/admin/tasks/{taskId}/images/{imageId}/download", name: "admin_task_image_download")]
    public function downloadTaskImageAction($taskId, $imageId,
        StorageInterface $storage,
        SlugifyInterface $slugify,
        Filesystem $taskImagesFilesystem)
    {
        $this->denyAccessUnlessGranted('ROLE_DISPATCHER');

        $image = $this->entityManager->getRepository(TaskImage::class)->find($imageId);

        if (!$image) {
            throw new NotFoundHttpException(sprintf('Image #%d not found', $imageId));
        }

        // @see https://symfonycasts.com/screencast/symfony-uploads/file-streaming

        // FIXME
        // It's not clean to use resolveUri()
        // but the problem is that resolvePath() returns the path with prefix,
        // while $taskImagesFilesystem is alreay aware of the prefix
        $imagePath = ltrim($storage->resolveUri($image, 'file'), '/');

        if (!$taskImagesFilesystem->fileExists($imagePath)) {
            throw new NotFoundHttpException(sprintf('Image at path "%s" not found', $imagePath));
        }

        $response = new StreamedResponse(function() use ($storage, $image) {
            $outputStream = fopen('php://output', 'wb');
            $fileStream = $storage->resolveStream($image, 'file');
            stream_copy_to_stream($fileStream, $outputStream);
        });

        $response->headers->set('Content-Type', $taskImagesFilesystem->mimeType($imagePath));

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $this->getImageDownloadFileName($image, $slugify)
        );
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    protected function getImageDownloadFileName(TaskImage $taskImage, SlugifyInterface $slugify)
    {
        $taskImageNamer = new TaskImageNamer($slugify);

        return $taskImageNamer->getImageDownloadFileName($taskImage);
    }
}
