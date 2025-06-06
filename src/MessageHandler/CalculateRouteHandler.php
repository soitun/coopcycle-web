<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Address;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\CollectionInterface as TaskCollectionInterface;
use AppBundle\Entity\TaskCollection;
use AppBundle\Message\CalculateRoute;
use AppBundle\Service\RoutingInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CalculateRouteHandler
{
    public $logger;
    private $objectManager;
    private $routing;

    public function __construct(
        EntityManagerInterface $objectManager,
        RoutingInterface $routing,
        LoggerInterface $logger)
    {
        $this->objectManager = $objectManager;
        $this->routing = $routing;
        $this->logger = $logger;
    }

    public function __invoke(CalculateRoute $message)
    {
        $address = $this->objectManager->getRepository(Address::class)
            ->find($message->getAddressId());

        if (!$address) {
            return;
        }

        $this->logger->debug(sprintf('%s : address #%d was updated', CalculateRoute::class, $message->getAddressId()));

        $tasks = $this->objectManager->getRepository(Task::class)
            ->findByAddress($address);

        if (count($tasks) === 0) {
            return;
        }

        $this->logger->debug(sprintf('%s : there are %d tasks linked to address #%d',
            CalculateRoute::class, count($tasks), $message->getAddressId()));

        $toUpdate = [];

        foreach ($tasks as $task) {

            $collections = $this->objectManager->getRepository(TaskCollection::class)
                ->findByTask($task);

            foreach ($collections as $collection) {
                $toUpdate[] = $collection;
            }
        }

        $this->logger->debug(sprintf('%s : there are %d collections to update',
            CalculateRoute::class, count($toUpdate)));

        if (count($toUpdate) === 0) {
            return;
        }

        foreach ($toUpdate as $collection) {
            $this->calculate($collection);
        }

        $this->objectManager->flush();
    }

    private function calculate(TaskCollectionInterface $taskCollection)
    {
        $coordinates = [];
        foreach ($taskCollection->getTasks() as $task) {
            $coordinates[] = $task->getAddress()->getGeo();
        }

        if (count($coordinates) <= 1) {
            $taskCollection->setDistance(0);
            $taskCollection->setDuration(0);
            $taskCollection->setPolyline('');
        } else {
            $taskCollection->setDistance($this->routing->getDistance(...$coordinates));
            $taskCollection->setDuration($this->routing->getDuration(...$coordinates));
            $taskCollection->setPolyline($this->routing->getPolyline(...$coordinates));
        }
    }
}
