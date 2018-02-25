<?php

namespace AppBundle\Entity\Task;

use AppBundle\Entity\Task;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

trait CollectionTrait
{
    /**
     * @ORM\Column(type="integer")
     */
    protected $distance;

    /**
     * @ORM\Column(type="integer")
     */
    protected $duration;

    public function getDistance()
    {
        return $this->distance;
    }

    public function setDistance($distance)
    {
        $this->distance = $distance;

        return $this;
    }

    public function getDuration()
    {
        return $this->duration;
    }

    public function setDuration($duration)
    {
        $this->duration = $duration;

        return $this;
    }

    public function getItems()
    {
        if (null === $this->items) {
            $this->items = new ArrayCollection();
        }

        return $this->items;
    }

    public function addItem(Task $task)
    {
        $item = $this->createItem();
        $item->setTask($task);
        $item->setCollection($this);
        $item->setPosition(-1);

        $this->getItems()->add($item);

        return $this;
    }
}
