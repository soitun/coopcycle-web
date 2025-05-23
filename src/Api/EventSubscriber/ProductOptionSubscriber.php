<?php

namespace AppBundle\Api\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use ApiPlatform\Symfony\EventListener\EventPriorities;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ProductOptionSubscriber implements EventSubscriberInterface
{
    private $entityManager;

    private static $routes = [
        // A restaurant can retrieve disabled option values
        '_api_/restaurants/{id}/product_options_get_collection',
        // A restaurant can re-enable a disabled option
        '_api_/product_option_values/{id}_put',
    ];

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['disableDoctrineFiter', EventPriorities::PRE_READ],
        ];
    }

    public function disableDoctrineFiter(RequestEvent $event)
    {
        $request = $event->getRequest();

        if (!in_array($request->attributes->get('_route'), self::$routes)) {

            return;
        }

        $filterCollection = $this->entityManager->getFilters();
        if ($filterCollection->isEnabled('disabled_filter')) {
            $filterCollection->disable('disabled_filter');
        }
    }
}
