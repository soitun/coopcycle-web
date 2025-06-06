<?php

namespace AppBundle\Message\Task\Command;

use AppBundle\Entity\Incident\Incident as IncidentObject;
use AppBundle\Entity\Task;

class Incident
{
    public function __construct(
        private Task $task,
        private string $reason,
        private ?string $notes = null,
        private array $data = [],
        private ?IncidentObject $incident = null
   )
    { }

    public function getTask(): Task
    {
        return $this->task;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getIncident(): ?IncidentObject
    {
        return $this->incident;
    }
}

