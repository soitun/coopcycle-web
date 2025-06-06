<?php

namespace AppBundle\Entity\Delivery;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Entity\Store;
use AppBundle\Action\Delivery\ImportQueueCsv as CsvController;
use AppBundle\Action\Delivery\ImportQueueRedownload as RedownloadController;
use AppBundle\Spreadsheet\SpreadsheetParseResult;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'DeliveryImportQueue',
    operations: [
        new Get(normalizationContext: ['groups' => ['delivery_import_queue']],
            security: 'is_granted(\'view\', object)'),
        new Get(
            uriTemplate: '/delivery_import_queues/{id}/csv',
            controller: CsvController::class,
            security: 'is_granted(\'view\', object)'
        ),
        new Get(
            uriTemplate: '/delivery_import_queues/{id}/redownload',
            controller: RedownloadController::class,
            security: 'is_granted(\'view\', object)'
        )
    ],
    normalizationContext: ['groups' => ['delivery_import_queue']]
)]
class ImportQueue
{
    use Timestampable;

    const STATUS_PENDING = 'pending';
    const STATUS_STARTED = 'started';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    protected $id;

    protected string $filename;

    protected Store $store;

    #[Assert\Type(type: 'string')]
    #[Groups(['delivery_import_queue'])]
    protected $status = self::STATUS_PENDING;

    protected $startedAt;

    protected $finishedAt;

    #[Groups(['delivery_import_queue'])]
    protected array $errors = [];

    public function getId()
    {
        return $this->id;
    }

    public function setStore(Store $store)
    {
        $this->store = $store;
    }

    public function getStore(): Store
    {
        return $this->store;
    }

    public function setFilename(string $filename)
    {
        $this->filename = $filename;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status)
    {
        $this->status = $status;
    }

    public function setStartedAt(\DateTime $startedAt)
    {
        $this->startedAt = $startedAt;
    }

    public function setFinishedAt(\DateTime $finishedAt)
    {
        $this->finishedAt = $finishedAt;
    }

    public function setErrors(array $errors)
    {
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
