<?php

namespace AppBundle\Entity\Task;

use AppBundle\Entity\Task;

interface CollectionInterface
{
    public function getItems();

    public function addItem(Task $task);

    public function getDistance();

    public function getDuration();
}
