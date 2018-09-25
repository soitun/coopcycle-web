<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Restaurant;
use AppBundle\Utils\RestaurantFilter;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;

class RestaurantRepository extends EntityRepository
{
    private $restaurantFilter;

    public function setRestaurantFilter(RestaurantFilter $restaurantFilter)
    {
        $this->restaurantFilter = $restaurantFilter;
    }

    // TODO : fix this to check that restaurants are really in delivery/radius zone
    private function createNearbyQueryBuilder($latitude, $longitude, $distance = 3500)
    {
        $qb = $this->createQueryBuilder('r');

        self::addNearbyQueryClause($qb, $latitude, $longitude, $distance);

        return $qb;
    }

    // TODO : fix this to check that restaurants are really in delivery/radius zone
    public static function addNearbyQueryClause(QueryBuilder $qb, $latitude, $longitude, $distance = 3500)
    {
        $qb->innerJoin($qb->getRootAlias() . '.address', 'a', Expr\Join::WITH);

        $geomFromText = new Expr\Func('ST_GeomFromText', array(
            $qb->expr()->literal("POINT({$latitude} {$longitude})"),
            '4326'
        ));

        $dist = new Expr\Func('ST_Distance', array(
            $geomFromText,
            'a.geo'
        ));

        // Add calculated distance field
        $qb->addSelect($dist . ' AS HIDDEN distance');

        $within = new Expr\Func('ST_DWithin', array(
            $geomFromText,
            'a.geo',
            $distance
        ));

        $qb->add('where', $qb->expr()->eq(
            $within,
            $qb->expr()->literal(true)
        ));
    }

    public function countNearby($latitude, $longitude, $distance = 5000, $limit = 10, $offset = 0)
    {
        $qb = $this->createNearbyQueryBuilder($latitude, $longitude, $distance);

        $qb->select($qb->expr()->count('r'));

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function findNearby($latitude, $longitude, $distance = 5000, $limit = 10, $offset = 0)
    {
        $qb = $this->createNearbyQueryBuilder($latitude, $longitude, $distance);

        $qb
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $qb->orderBy('distance');

        return $qb->getQuery()->getResult();
    }

    /**
     * We will obsviously have a fairly small amount of restaurants.
     * So, there is not significant performance downside in loading them all.
     * Event with 50 restaurants, it takes ~ 500ms to complete.
     */
    public function findByLatLng($latitude, $longitude)
    {
        return $this->restaurantFilter->matchingLatLng($this->findAll(), $latitude, $longitude);
    }

    public function search($q)
    {
        $qb = $this->createQueryBuilder('r');

        $qb
            ->where('LOWER(r.name) LIKE :q')
            ->setParameter('q', '%' . strtolower($q) . '%');

        return $qb->getQuery()->getResult();
    }

    public function findRandom($maxResults = 3)
    {
        // Do not use ORDER BY RAND()
        // @see https://github.com/doctrine/doctrine2/issues/5479
        $qb = $this->createQueryBuilder('r');

        $rows = $qb
            ->select('r.id')
            ->getQuery()
            ->getArrayResult();

        shuffle($rows);

        $rows = array_slice($rows, 0, $maxResults);

        $ids = array_map(function ($row) {
            return $row['id'];
        }, $rows);

        return $this->createQueryBuilder('r')
            ->where('r.id IN (:ids)')
            ->setParameter('ids', array_values($ids))
            ->getQuery()
            ->getResult();
    }
}
