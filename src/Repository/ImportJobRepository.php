<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ImportJob;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ImportJob>
 */
class ImportJobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportJob::class);
    }

    /**
     * @return ImportJob[]
     */
    public function findOlderThan(int $days, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('j')
            ->where('j.startedAt < :cutoff')
            ->setParameter('cutoff', new \DateTimeImmutable("-{$days} days"));

        if ($status !== null) {
            $qb->andWhere('j.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function deleteLogsForJobs(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->getEntityManager()->getConnection()->executeStatement(
            "DELETE FROM import_log WHERE import_job_id IN ($placeholders)",
            $ids,
        );
    }

    public function deleteOldJobs(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $this->createQueryBuilder('j')
            ->delete()
            ->where('j.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }
}
