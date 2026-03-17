<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\ImportJob;
use App\Entity\ImportLog;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ImportCleanupCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->em->getConnection()->executeStatement(
            'TRUNCATE TABLE import_log, product, import_job RESTART IDENTITY CASCADE'
        );

        $command = new Application(self::$kernel)->find('import:cleanup');
        $this->commandTester = new CommandTester($command);
    }

    private function createJob(string $status, int $daysAgo): ImportJob
    {
        $job = (new ImportJob())
            ->setName("job-{$daysAgo}d.csv")
            ->setStatus($status)
            ->setSourceType(ImportJob::SOURCE_TYPE_CSV);

        $this->em->persist($job);
        $this->em->flush();

        // Backdate startedAt via raw SQL since it's set in the constructor
        $this->em->getConnection()->executeStatement(
            'UPDATE import_job SET started_at = :date WHERE id = :id',
            ['date' => (new DateTimeImmutable("-{$daysAgo} days"))->format('Y-m-d H:i:s'), 'id' => $job->getId()],
        );
        $this->em->clear();

        return $this->em->find(ImportJob::class, $job->getId());
    }

    private function createLogFor(ImportJob $job): ImportLog
    {
        $log = (new ImportLog())
            ->setImportJob($job)
            ->setLevel('error')
            ->setMessage('Something went wrong')
            ->setRowNumber(1);

        $this->em->persist($log);
        $this->em->flush();

        return $log;
    }

    public function testDeletesOldJobsAndLogs(): void
    {
        $old = $this->createJob(ImportJob::STATUS_COMPLETED, 40);
        $this->createLogFor($old);

        $code = $this->commandTester->execute([]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Deleted 1 job(s)', $this->commandTester->getDisplay());
        $this->em->clear();
        $this->assertNull($this->em->find(ImportJob::class, $old->getId()));
        $this->assertCount(0, $this->em->getRepository(ImportLog::class)->findAll());
    }

    public function testDoesNotDeleteRecentJobs(): void
    {
        $recent = $this->createJob(ImportJob::STATUS_COMPLETED, 5);

        $code = $this->commandTester->execute([]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('No matching jobs found', $this->commandTester->getDisplay());
        $this->assertNotNull($this->em->find(ImportJob::class, $recent->getId()));
    }

    public function testDryRunDoesNotDelete(): void
    {
        $old = $this->createJob(ImportJob::STATUS_COMPLETED, 40);

        $code = $this->commandTester->execute(['--dry-run' => true]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('would be deleted', $this->commandTester->getDisplay());
        $this->assertNotNull($this->em->find(ImportJob::class, $old->getId()));
    }

    public function testFilterByStatus(): void
    {
        $completed = $this->createJob(ImportJob::STATUS_COMPLETED, 40);
        $failed = $this->createJob('failed', 40);

        $code = $this->commandTester->execute(['--status' => 'completed']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Deleted 1 job(s)', $this->commandTester->getDisplay());
        $this->em->clear();
        $this->assertNull($this->em->find(ImportJob::class, $completed->getId()));
        $this->assertNotNull($this->em->find(ImportJob::class, $failed->getId()));
    }

    public function testInvalidStatusReturnsFailure(): void
    {
        $code = $this->commandTester->execute(['--status' => 'invalid']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('must be "completed" or "failed"', $this->commandTester->getDisplay());
    }

    public function testOlderThanOption(): void
    {
        $old = $this->createJob(ImportJob::STATUS_COMPLETED, 10);
        $older = $this->createJob(ImportJob::STATUS_COMPLETED, 20);

        // Only delete jobs older than 15 days
        $code = $this->commandTester->execute(['--older-than' => 15]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Deleted 1 job(s)', $this->commandTester->getDisplay());
        $this->em->clear();
        $this->assertNotNull($this->em->find(ImportJob::class, $old->getId()));
        $this->assertNull($this->em->find(ImportJob::class, $older->getId()));
    }
}
