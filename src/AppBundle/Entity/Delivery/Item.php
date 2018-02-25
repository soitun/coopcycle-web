<?php

namespace AppBundle\Entity\Delivery;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task\CollectionInterface;
use AppBundle\Entity\Task\Collection\Item as BaseItem;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity
 * @ORM\Table(name="delivery_item")
 */
class Item extends BaseItem
{
    /**
     * @Gedmo\SortableGroup
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Delivery", inversedBy="items", cascade={"persist"})
     * @ORM\JoinColumn(name="delivery_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $delivery;

    public function setCollection(CollectionInterface $collection)
    {
        $this->delivery = $collection;

        return $this;
    }
}
