<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\ImportJob;
use App\Entity\ImportLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ImportStatusCommandTest extends KernelTestCase
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

        $command = new Application(self::$kernel)->find('import:status');
        $this->commandTester = new CommandTester($command);
    }

    private function createJob(string $name, string $status, int $processed = 0, int $failed = 0): ImportJob
    {
        $job = (new ImportJob())
            ->setName($name)
            ->setStatus($status)
            ->setSourceType(ImportJob::SOURCE_TYPE_CSV)
            ->setProcessedRows($processed)
            ->setFailedRows($failed);

        $this->em->persist($job);
        $this->em->flush();

        return $job;
    }

    public function testListJobsShowsTable(): void
    {
        $this->createJob('products.csv', ImportJob::STATUS_COMPLETED, 10, 1);
        $this->createJob('other.csv', ImportJob::STATUS_RUNNING, 5, 0);

        $code = $this->commandTester->execute([]);

        $this->assertSame(0, $code);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('products.csv', $output);
        $this->assertStringContainsString('other.csv', $output);
        $this->assertStringContainsString('completed', $output);
        $this->assertStringContainsString('running', $output);
    }

    public function testListJobsWithNoJobsShowsInfo(): void
    {
        $code = $this->commandTester->execute([]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('No import jobs found', $this->commandTester->getDisplay());
    }

    public function testDetailViewShowsJobInfo(): void
    {
        $job = $this->createJob('products.csv', ImportJob::STATUS_COMPLETED, 20, 2);

        $code = $this->commandTester->execute(['job-id' => $job->getId()]);

        $this->assertSame(0, $code);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('products.csv', $output);
        $this->assertStringContainsString('completed', $output);
        $this->assertStringContainsString('No log entries', $output);
    }

    public function testDetailViewShowsLogEntries(): void
    {
        $job = $this->createJob('products.csv', ImportJob::STATUS_COMPLETED, 3, 1);

        $log = (new ImportLog())
            ->setImportJob($job)
            ->setLevel('error')
            ->setMessage('Invalid price value')
            ->setRowNumber(2);
        $this->em->persist($log);
        $this->em->flush();

        $code = $this->commandTester->execute(['job-id' => $job->getId()]);

        $this->assertSame(0, $code);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Invalid price value', $output);
        $this->assertStringContainsString('ERROR', $output);
    }

    public function testDetailViewFiltersByLevel(): void
    {
        $job = $this->createJob('products.csv', ImportJob::STATUS_COMPLETED, 3, 1);

        $errorLog = (new ImportLog())
            ->setImportJob($job)
            ->setLevel('error')
            ->setMessage('Bad price');
        $this->em->persist($errorLog);

        $infoLog = (new ImportLog())
            ->setImportJob($job)
            ->setLevel('info')
            ->setMessage('Row skipped');
        $this->em->persist($infoLog);
        $this->em->flush();

        $code = $this->commandTester->execute(['job-id' => $job->getId(), '--level' => 'error']);

        $this->assertSame(0, $code);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Bad price', $output);
        $this->assertStringNotContainsString('Row skipped', $output);
    }

    public function testDetailViewReturnsFailureForMissingJob(): void
    {
        $code = $this->commandTester->execute(['job-id' => 9999]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('not found', $this->commandTester->getDisplay());
    }
}
