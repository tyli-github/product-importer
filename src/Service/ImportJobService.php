<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\ImportResult;
use App\Entity\ImportJob;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

readonly class ImportJobService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function createJob(string $filePath, string $sourceType = ImportJob::SOURCE_TYPE_CSV): ImportJob
    {
        $job = new ImportJob();
        $job->setName(basename($filePath));
        $job->setStatus(ImportJob::STATUS_PENDING);
        $job->setSourceType($sourceType);
        $job->setFilePath($filePath);

        $this->entityManager->persist($job);
        $this->entityManager->flush();

        return $job;
    }

    public function updateJobResults(ImportJob $job, ImportResult $result): void
    {
        $job->setStatus(ImportJob::STATUS_COMPLETED);
        $job->setCompletedAt(new DateTimeImmutable());
        $job->setProcessedRows($result->processed);
        $job->setFailedRows($result->failed);
        $job->setUpdatedRows($result->updated);
        $job->setTotalRows($result->total());

        $this->entityManager->flush();
    }
}
