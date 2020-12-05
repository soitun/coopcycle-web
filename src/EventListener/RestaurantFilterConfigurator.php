<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Types\Type;

class RestaurantFilterConfigurator
{
    protected $em;
    protected $tokenStorage;
    protected $restaurantRepository;
    protected $reader;
    protected $cache;

    public function __construct(
        EntityManagerInterface $em,
        TokenStorageInterface $tokenStorage,
        LocalBusinessRepository $restaurantRepository,
        CacheInterface $enabledFilterConfiguratorCache)
    {
        $this->em = $em;
        $this->tokenStorage = $tokenStorage;
        $this->restaurantRepository = $restaurantRepository;
        $this->cache = $enabledFilterConfiguratorCache;
    }

    public function onKernelRequest()
    {
        $restaurants = [];

        if ($user = $this->getUser()) {

            // If this is an admin, we don't enable the filter
            if ($user->hasRole('ROLE_ADMIN')) {
                return;
            }

            $restaurants = $this->cache->get($user->getUsername(), function (ItemInterface $item) use ($user) {

                $item->expiresAfter(600);

                $restaurants = [];
                if ($user->hasRole('ROLE_RESTAURANT')) {
                    $restaurants = $user->getRestaurants()->toArray();
                }

                return array_map(function (LocalBusiness $restaurant) {
                    return $restaurant->getId();
                }, $restaurants);
            });
        }

        $filter = $this->em->getFilters()->enable('restaurant_filter');
        $filter->setParameter('enabled', true, Type::BOOLEAN);

        if (count($restaurants) > 0) {
            $filter->setParameter('restaurants', $restaurants, Type::SIMPLE_ARRAY);
        }
    }

    private function getUser()
    {
        if (null === $token = $this->tokenStorage->getToken()) {
            return;
        }

        if (!is_object($user = $token->getUser())) {
            return;
        }

        return $user;
    }
}