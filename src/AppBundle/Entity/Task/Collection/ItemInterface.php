<?php

namespace AppBundle\Entity\Task\Collection;

interface ItemInterface
{
    public function getTask();

    public function getPosition();
}
