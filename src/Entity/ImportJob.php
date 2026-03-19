<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ImportJobRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImportJobRepository::class)]
class ImportJob
{
    public const string SOURCE_TYPE_CSV = 'csv';

    public const string SOURCE_TYPE_JSON = 'json';

    public const string SOURCE_TYPE_HTTP = 'http';

    public const string STATUS_COMPLETED = 'completed';

    public const string STATUS_FAILED = 'failed';

    public const string STATUS_RUNNING = 'running';

    public const string STATUS_PENDING = 'pending';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column(length: 20)]
    private ?string $sourceType = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column(nullable: true)]
    private ?int $totalRows = null;

    #[ORM\Column(nullable: true)]
    private ?int $processedRows = null;

    #[ORM\Column(nullable: true)]
    private ?int $failedRows = null;

    #[ORM\Column(nullable: true)]
    private ?int $updatedRows = null;

    #[ORM\Column]
    private DateTimeImmutable $startedAt;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $completedAt = null;

    public function __construct()
    {
        $this->startedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getSourceType(): ?string
    {
        return $this->sourceType;
    }

    public function setSourceType(string $sourceType): static
    {
        $this->sourceType = $sourceType;

        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): static
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getTotalRows(): ?int
    {
        return $this->totalRows;
    }

    public function setTotalRows(?int $totalRows): static
    {
        $this->totalRows = $totalRows;

        return $this;
    }

    public function getProcessedRows(): ?int
    {
        return $this->processedRows;
    }

    public function setProcessedRows(?int $processedRows): static
    {
        $this->processedRows = $processedRows;

        return $this;
    }

    public function getFailedRows(): ?int
    {
        return $this->failedRows;
    }

    public function setFailedRows(?int $failedRows): static
    {
        $this->failedRows = $failedRows;

        return $this;
    }

    public function getUpdatedRows(): ?int
    {
        return $this->updatedRows;
    }

    public function setUpdatedRows(?int $updatedRows): static
    {
        $this->updatedRows = $updatedRows;

        return $this;
    }

    public function getStartedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }
}
