<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ImportLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImportLogRepository::class)]
class ImportLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ImportJob::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?ImportJob $importJob = null;

    #[ORM\Column(length: 20)]
    private ?string $level = null;

    #[ORM\Column(type: 'text')]
    private ?string $message = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(nullable: true)]
    private ?int $rowNumber = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $context = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getImportJob(): ?ImportJob
    {
        return $this->importJob;
    }

    public function setImportJob(ImportJob $importJob): static
    {
        $this->importJob = $importJob;

        return $this;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(string $level): static
    {
        $this->level = $level;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getRowNumber(): ?int
    {
        return $this->rowNumber;
    }

    public function setRowNumber(?int $rowNumber): static
    {
        $this->rowNumber = $rowNumber;

        return $this;
    }

    public function getContext(): ?array
    {
        return $this->context;
    }

    public function setContext(?array $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
