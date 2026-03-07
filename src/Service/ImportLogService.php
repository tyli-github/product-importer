<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ImportJob;
use App\Entity\ImportLog;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

readonly class ImportLogService
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private LoggerInterface $logger,
    ) {
    }

    public function create(ImportJob $job, string $level, string $message, ?int $row, array $context): void
    {
        $entityManager = $this->getEntityManager();

        $managedJob = $entityManager->find(ImportJob::class, $job->getId());
        if ($managedJob === null) {
            $this->logger->warning('Could not reload import job for log entry', [
                'job_id' => $job->getId(),
                'row' => $row,
                'message' => $message,
            ]);

            return;
        }

        $log = new ImportLog();
        $log->setImportJob($managedJob);
        $log->setLevel($level);
        $log->setMessage($message);
        $log->setRowNumber($row);
        $log->setContext($context);

        $entityManager->persist($log);
        $entityManager->flush();
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return $this->managerRegistry->getManager();
    }
}
