<?php

namespace AppBundle\Sylius\Order;

use Sylius\Component\Order\OrderTransitions as BaseOrderTransitions;

class OrderTransitions
{
    public const GRAPH = BaseOrderTransitions::GRAPH;

    public const TRANSITION_CREATE  = BaseOrderTransitions::TRANSITION_CREATE;
    public const TRANSITION_CANCEL  = BaseOrderTransitions::TRANSITION_CANCEL;
    public const TRANSITION_FULFILL = BaseOrderTransitions::TRANSITION_FULFILL;

    public const TRANSITION_ACCEPT  = 'accept';
    public const TRANSITION_REFUSE  = 'refuse';
    public const TRANSITION_START_PREPARING  = 'start_preparing';
    public const TRANSITION_FINISH_PREPARING  = 'finish_preparing';
    public const TRANSITION_RESTORE = 'restore';
}
