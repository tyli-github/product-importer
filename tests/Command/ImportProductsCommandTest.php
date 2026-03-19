<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Repository\ImportJobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ImportProductsCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;

    private ImportJobRepository $jobRepository;

    private string $tmpDir;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $command = new Application(self::$kernel)->find('import:products');
        $entityManager = $container->get(EntityManagerInterface::class);

        $this->commandTester = new CommandTester($command);
        $this->jobRepository = $container->get(ImportJobRepository::class);
        $this->tmpDir = sys_get_temp_dir();

        $conn = $entityManager->getConnection();
        $conn->executeStatement('TRUNCATE TABLE import_log, product, import_job RESTART IDENTITY CASCADE');
    }

    private function writeCsv(string $content): string
    {
        $path = $this->tmpDir . '/import_' . uniqid() . '.csv';
        file_put_contents($path, $content);

        return $path;
    }

    public function testQueuesImportJob(): void
    {
        $path = $this->writeCsv("name,sku,price\nWidget A,SKU-001,9.99\nWidget B,SKU-002,19.99\n");

        $code = $this->commandTester->execute(['file' => $path]);

        $this->assertSame(0, $code);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Import queued', $output);

        $jobs = $this->jobRepository->findAll();
        $this->assertCount(1, $jobs);
        $job = $jobs[0];
        $this->assertSame('pending', $job->getStatus());
        $this->assertNull($job->getProcessedRows());
        $this->assertNull($job->getCompletedAt());
    }

    public function testFailsOnMissingFile(): void
    {
        $code = $this->commandTester->execute(['file' => '/nonexistent/file.csv']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('No reader supports the source', $this->commandTester->getDisplay());
    }

    public function testDryRunQueuesWithDryRunFlag(): void
    {
        $path = $this->writeCsv("name,sku,price\nWidget A,SKU-001,9.99\n");

        $code = $this->commandTester->execute(['file' => $path, '--dry-run' => true]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Import queued', $this->commandTester->getDisplay());

        $jobs = $this->jobRepository->findAll();
        $this->assertCount(1, $jobs);
        $this->assertSame('pending', $jobs[0]->getStatus());
    }

    public function testQueuesJobWithInvalidData(): void
    {
        $path = $this->writeCsv("name,sku,price\n,INVALID,bad\n");

        $code = $this->commandTester->execute(['file' => $path]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Import queued', $this->commandTester->getDisplay());

        $jobs = $this->jobRepository->findAll();
        $this->assertCount(1, $jobs);
        $this->assertSame('pending', $jobs[0]->getStatus());
    }
}
