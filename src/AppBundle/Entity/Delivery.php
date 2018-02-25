<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Entity\Model\TaxableTrait;
use AppBundle\Entity\Delivery\Item as DeliveryItem;
use AppBundle\Entity\Task\CollectionInterface as TaskCollectionInterface;
use AppBundle\Entity\Task\CollectionTrait as TaskCollectionTrait;
use AppBundle\Validator\Constraints\Delivery as AssertDelivery;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;
use Sylius\Component\Taxation\Model\TaxableInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @see http://schema.org/ParcelDelivery Documentation on Schema.org
 *
 * @ORM\Entity(repositoryClass="AppBundle\Entity\DeliveryRepository")
 * @ORM\EntityListeners({"AppBundle\Entity\Listener\DeliveryListener"})
 * @ApiResource(iri="http://schema.org/ParcelDelivery",
 *   collectionOperations={},
 *   itemOperations={
 *     "get"={"method"="GET"}
 *   },
 *   attributes={
 *     "denormalization_context"={"groups"={"order_create"}},
 *     "normalization_context"={"groups"={"delivery", "place", "order"}}
 *   }
 * )
 * @AssertDelivery
 */
class Delivery implements TaskCollectionInterface, TaxableInterface
{
    use TaskCollectionTrait;
    use TaxableTrait;

    // default status when the delivery is created along the order
    const STATUS_WAITING    = 'WAITING';
    // the delivery has been accepted by a courier
    const STATUS_DISPATCHED = 'DISPATCHED';
    // the delivery has been picked by a courier
    const STATUS_PICKED     = 'PICKED';
    // delivered successfully
    const STATUS_DELIVERED  = 'DELIVERED';
    // the courier had an accident
    const STATUS_ACCIDENT   = 'ACCIDENT';
    // delivery was canceled (by an admin)
    const STATUS_CANCELED   = 'CANCELED';

    const VEHICLE_BIKE = 'bike';
    const VEHICLE_CARGO_BIKE = 'cargo_bike';

    /**
     * @var int
     *
     * @Groups({"delivery"})
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     */
    private $originAddress;

    /**
     * @Groups({"order_create"})
     */
    private $deliveryAddress;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Delivery\Item", mappedBy="delivery", cascade={"all"})
     */
    private $items;

    /**
     * @ORM\OneToOne(targetEntity="Order", inversedBy="delivery")
     * @ORM\JoinColumn(name="order_id", referencedColumnName="id")
     */
    private $order;

    /**
     * @ORM\ManyToOne(targetEntity="Store", inversedBy="deliveries")
     */
    private $store;

    /**
     * @var string
     *
     * @Groups({"delivery", "order"})
     * @ORM\Column(type="string", nullable=true)
     */
    private $status;

    /**
     * @Groups({"order_create"})
     */
    private $date;

    /**
     * @ORM\OneToMany(targetEntity="DeliveryEvent", mappedBy="delivery")
     * @ORM\OrderBy({"createdAt" = "ASC"})
     */
    private $events;

    /**
     * @ORM\ManyToOne(targetEntity="Sylius\Component\Taxation\Model\TaxCategoryInterface")
     * @ORM\JoinColumn(name="tax_category_id", referencedColumnName="id", nullable=false)
     */
    private $taxCategory;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $weight;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $vehicle;

    public function __construct(Order $order = null)
    {
        $this->status = self::STATUS_WAITING;

        if ($order) {
            $this->setOrder($order);
            $order->setDelivery($this);
        }

        $this->events = new ArrayCollection();
    }

    /**
     * Gets id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    public function getOriginAddress()
    {
        return $this->originAddress;
    }

    public function setOriginAddress(Address $originAddress)
    {
        $this->originAddress = $originAddress;

        return $this;
    }

    public function setOriginAddressFromOrder(Order $order)
    {
        if (null !== $order->getRestaurant()) {
            $this->originAddress = $order->getRestaurant()->getAddress();
        }
    }

    public function getDeliveryAddress()
    {
        return $this->deliveryAddress;
    }

    public function setDeliveryAddress(Address $deliveryAddress)
    {
        $this->deliveryAddress = $deliveryAddress;

        return $this;
    }

    public function getDate()
    {
        return $this->date;
    }

    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function setOrder(Order $order)
    {
        $this->setPriceFromOrder($order);
        $this->setOriginAddressFromOrder($order);

        $this->order = $order;

        return $this;
    }

    public function isCalculated()
    {
        return null !== $this->duration && null !== $this->distance;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return ArrayCollection|OrderEvent[]
    */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * @deprecated
     */
    public function getPrice()
    {
        return $this->getTotalIncludingTax();
    }

    public function setPriceFromOrder(Order $order)
    {
        if (null !== $order->getRestaurant()) {
            $this->setTotalIncludingTax($order->getRestaurant()->getFlatDeliveryPrice());
        }
    }

    public function getTaxCategory(): ?TaxCategoryInterface
    {
        return $this->taxCategory;
    }

    public function setTaxCategory(TaxCategoryInterface $taxCategory)
    {
        $this->taxCategory = $taxCategory;

        return $this;
    }

    public function getWeight()
    {
        return $this->weight;
    }

    public function setWeight($weight)
    {
        $this->weight = $weight;

        return $this;
    }

    public function getActualDuration()
    {
        if ($this->status === self::STATUS_DELIVERED) {

            $criteria = Criteria::create()
                ->andWhere(Criteria::expr()->eq('eventName', self::STATUS_DISPATCHED));
            $dispatched = $this->events->matching($criteria)->first();

            $criteria = Criteria::create()
                ->andWhere(Criteria::expr()->eq('eventName', self::STATUS_DELIVERED));
            $delivered = $this->events->matching($criteria)->first();

            if ($dispatched && $delivered) {
                $diff = $delivered->getCreatedAt()->diff($dispatched->getCreatedAt());

                $hours = $diff->format('%h');
                $minutes = $diff->format('%i');

                return $hours > 0 ? "{$hours}h {$minutes}min" : "{$minutes}min";
            }
        }
    }

    public function getVehicle()
    {
        return $this->vehicle;
    }

    public function setVehicle($vehicle)
    {
        $this->vehicle = $vehicle;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getStore()
    {
        return $this->store;
    }

    /**
     * @param mixed $store
     */
    public function setStore($store)
    {
        $this->store = $store;
    }

    public function createItem()
    {
        return new DeliveryItem();
    }

    public static function createTasks(Delivery $delivery)
    {
        $dropoffDoneBefore = clone $delivery->getDate();

        $dropoffDoneAfter = clone $delivery->getDate();
        $dropoffDoneAfter->modify('-15 minutes');

        $dropoffTask = new Task();
        $dropoffTask->setType(Task::TYPE_DROPOFF);
        $dropoffTask->setAddress($delivery->getDeliveryAddress());
        $dropoffTask->setDoneAfter($dropoffDoneAfter);
        $dropoffTask->setDoneBefore($dropoffDoneBefore);

        $pickupDoneBefore = clone $delivery->getDate();
        $pickupDoneBefore->modify(sprintf('-%d seconds', $delivery->getDuration()));

        $pickupDoneAfter = clone $pickupDoneBefore;
        $pickupDoneAfter->modify('-15 minutes');

        $pickupTask = new Task();
        $pickupTask->setType(Task::TYPE_PICKUP);
        $pickupTask->setAddress($delivery->getOriginAddress());
        $pickupTask->setDoneAfter($pickupDoneAfter);
        $pickupTask->setDoneBefore($pickupDoneBefore);

        $dropoffTask->setPrevious($pickupTask);

        return [ $pickupTask, $dropoffTask ];
    }
}
