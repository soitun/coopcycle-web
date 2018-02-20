<?php

namespace AppBundle\Entity\Delivery;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="delivery_step", uniqueConstraints={
 *   @ORM\UniqueConstraint(name="delivery_step_delivery_task_unique", columns={"delivery_id", "task_id"})}
 * )
 */
class Step
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Delivery", inversedBy="steps")
     * @ORM\JoinColumn(name="delivery_id", referencedColumnName="id", nullable=false)
     */
    private $delivery;

    /**
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\Task", cascade={"persist"})
     * @ORM\JoinColumn(name="task_id", referencedColumnName="id", nullable=false)
     */
    private $task;

    public function getDelivery()
    {
        return $this->delivery;
    }

    public function setDelivery($delivery)
    {
        $this->delivery = $delivery;

        return $this;
    }

    public function getTask()
    {
        return $this->task;
    }

    public function setTask($task)
    {
        $this->task = $task;

        return $this;
    }
}
