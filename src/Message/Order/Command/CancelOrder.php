<?php

namespace AppBundle\Message\Order\Command;

use AppBundle\Sylius\Order\OrderInterface;

class CancelOrder
{
    private $order;
    private $reason;

    public function __construct(OrderInterface $order, $reason = null)
    {
        $this->order = $order;
        $this->reason = $reason;
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function getReason()
    {
        return $this->reason;
    }
}
