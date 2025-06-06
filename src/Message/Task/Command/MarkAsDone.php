<?php

namespace AppBundle\Message\Task\Command;

use AppBundle\Entity\Task;

class MarkAsDone
{
    private $task;
    private $notes;
    private $contactName;

    public function __construct(Task $task, $notes = null, $contactName = null)
    {
        $this->task = $task;
        $this->notes = $notes;
        $this->contactName = $contactName;
    }

    public function getTask()
    {
        return $this->task;
    }

    public function getNotes()
    {
        return $this->notes;
    }

    public function getContactName()
    {
        return $this->contactName;
    }
}
