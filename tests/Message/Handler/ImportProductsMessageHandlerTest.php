<?php

declare(strict_types=1);

namespace App\Tests\Message\Handler;

use App\Entity\ImportJob;
use App\Message\ImportProductsMessage;
use App\Message\Handler\ImportProductsMessageHandler;
use App\Repository\ImportJobRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ImportProductsMessageHandlerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private ImportJobRepository $jobRepository;

    private ProductRepository $productRepository;

    private string $tmpDir;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->jobRepository = $container->get(ImportJobRepository::class);
        $this->productRepository = $container->get(ProductRepository::class);
        $this->tmpDir = sys_get_temp_dir();

        $conn = $this->entityManager->getConnection();
        $conn->executeStatement('TRUNCATE TABLE import_log, product, import_job RESTART IDENTITY CASCADE');
    }

    private function writeCsv(string $content): string
    {
        $path = $this->tmpDir . '/import_' . uniqid() . '.csv';
        file_put_contents($path, $content);

        return $path;
    }

    public function testHandlerProcessesImportMessage(): void
    {
        $path = $this->writeCsv("name,sku,price\nWidget A,SKU-001,9.99\nWidget B,SKU-002,19.99\n");

        $job = new ImportJob();
        $job->setName(basename($path));
        $job->setStatus(ImportJob::STATUS_PENDING);
        $job->setSourceType(ImportJob::SOURCE_TYPE_CSV);
        $job->setFilePath($path);

        $this->entityManager->persist($job);
        $this->entityManager->flush();

        $handler = static::getContainer()->get(ImportProductsMessageHandler::class);
        $message = new ImportProductsMessage(
            jobId: $job->getId(),
            source: $path,
            dryRun: false,
        );

        $handler($message);

        $refreshed = $this->jobRepository->find($job->getId());
        $this->assertSame(ImportJob::STATUS_COMPLETED, $refreshed->getStatus());
        $this->assertSame(2, $refreshed->getProcessedRows());
        $this->assertNotNull($refreshed->getCompletedAt());

        $products = $this->productRepository->findAll();
        $this->assertCount(2, $products);
    }

    public function testHandlerHandlesDryRun(): void
    {
        $path = $this->writeCsv("name,sku,price\nWidget A,SKU-001,9.99\n");

        $job = new ImportJob();
        $job->setName(basename($path));
        $job->setStatus(ImportJob::STATUS_PENDING);
        $job->setSourceType(ImportJob::SOURCE_TYPE_CSV);
        $job->setFilePath($path);

        $this->entityManager->persist($job);
        $this->entityManager->flush();

        $handler = static::getContainer()->get(ImportProductsMessageHandler::class);
        $message = new ImportProductsMessage(
            jobId: $job->getId(),
            source: $path,
            dryRun: true,
        );

        $handler($message);

        $refreshed = $this->jobRepository->find($job->getId());
        $this->assertSame(ImportJob::STATUS_COMPLETED, $refreshed->getStatus());
        $this->assertSame(1, $refreshed->getProcessedRows());

        $products = $this->productRepository->findAll();
        $this->assertCount(0, $products);
    }

    public function testHandlerMarksJobFailedOnMissingFile(): void
    {
        $job = new ImportJob();
        $job->setName('missing.csv');
        $job->setStatus(ImportJob::STATUS_PENDING);
        $job->setSourceType(ImportJob::SOURCE_TYPE_CSV);
        $job->setFilePath('/nonexistent/file.csv');

        $this->entityManager->persist($job);
        $this->entityManager->flush();

        $handler = static::getContainer()->get(ImportProductsMessageHandler::class);
        $message = new ImportProductsMessage(
            jobId: $job->getId(),
            source: '/nonexistent/file.csv',
            dryRun: false,
        );

        $this->expectException(\Throwable::class);

        try {
            $handler($message);
        } finally {
            $refreshed = $this->jobRepository->find($job->getId());
            $this->assertSame(ImportJob::STATUS_FAILED, $refreshed->getStatus());
            $this->assertNotNull($refreshed->getCompletedAt());
        }
    }
}
