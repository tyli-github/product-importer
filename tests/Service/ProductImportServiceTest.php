<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DTO\ProductImportContext;
use App\Entity\ImportJob;
use App\Event\ProductImportedEvent;
use App\Interface\ImportReaderInterface;
use App\Repository\ProductRepository;
use App\Service\ImportLogService;
use App\Service\ProductImportService;
use App\Service\ProductValidator;
use App\Service\Reader\CsvImportReader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductImportServiceTest extends TestCase
{
    private ManagerRegistry&Stub $managerRegistry;

    private EntityManagerInterface&MockObject $entityManager;

    private ProductValidator&Stub $productValidator;

    private ProductRepository&Stub $productRepository;

    private ImportLogService&Stub $importLogService;

    private EventDispatcherInterface $eventDispatcher;

    private ImportReaderInterface $reader;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->managerRegistry = $this->createStub(ManagerRegistry::class);
        $this->productValidator = $this->createStub(ProductValidator::class);
        $this->productRepository = $this->createStub(ProductRepository::class);
        $this->importLogService = $this->createStub(ImportLogService::class);
        $this->eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $this->reader = new CsvImportReader(new NullLogger());
        $this->tmpDir = sys_get_temp_dir();

        $this->managerRegistry->method('getManager')->willReturn($this->entityManager);
    }

    private function makeService(): ProductImportService
    {
        return new ProductImportService(
            $this->managerRegistry,
            $this->productValidator,
            $this->productRepository,
            $this->reader,
            new NullLogger(),
            $this->importLogService,
            $this->eventDispatcher,
        );
    }

    private function writeCsv(string $content): string
    {
        $path = $this->tmpDir . '/import_' . uniqid() . '.csv';
        file_put_contents($path, $content);

        return $path;
    }

    private function makeJob(): ImportJob
    {
        $job = new ImportJob();
        $job->setName('test-import');
        $job->setStatus('running');
        $job->setSourceType('csv');

        return $job;
    }

    private function makeContext(bool $dryRun = false, bool $allowUpdates = false): ProductImportContext
    {
        return new ProductImportContext($dryRun, $allowUpdates);
    }

    public function testImportsValidRowsSuccessfully(): void
    {
        $path = $this->writeCsv("name,sku,price\nWidget A,SKU-001,9.99\nWidget B,SKU-002,19.99\n");

        $this->productValidator->method('validate')->willReturn([]);
        $this->productRepository->method('findBySku')->willReturn(null);
        $this->entityManager->expects($this->exactly(2))->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->makeService()->import($path, $this->makeJob(), $this->makeContext());

        $this->assertSame(2, $result->processed);
        $this->assertSame(0, $result->failed);
        $this->assertSame(0, $result->skipped);
        $this->assertSame(2, $result->total());
    }

    public function testCountsFailedRowsOnValidationError(): void
    {
        $path = $this->writeCsv("name,sku,price\n,INVALID,bad\n");

        $this->productValidator->method('validate')->willReturn(['name: This value should not be blank.']);

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $result = $this->makeService()->import($path, $this->makeJob(), $this->makeContext());

        $this->assertSame(0, $result->processed);
        $this->assertSame(1, $result->failed);
    }

    public function testDryRunDoesNotPersist(): void
    {
        $path = $this->writeCsv("name,sku,price\nWidget A,SKU-001,9.99\n");

        $this->productValidator->method('validate')->willReturn([]);
        $this->productRepository->method('findBySku')->willReturn(null);
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $result = $this->makeService()->import($path, $this->makeJob(), $this->makeContext(dryRun: true));

        $this->assertSame(1, $result->processed);
    }

    public function testSkipsAllEmptyRows(): void
    {
        $path = $this->writeCsv("name,sku,price\n,,\n");

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $result = $this->makeService()->import($path, $this->makeJob(), $this->makeContext());

        $this->assertSame(0, $result->processed);
        $this->assertSame(1, $result->skipped);
    }

    public function testSkipsDuplicateSku(): void
    {
        $path = $this->writeCsv("name,sku,price\nWidget A,SKU-001,9.99\n");

        $this->productValidator->method('validate')->willReturn([]);
        // Simulate product already exists in database
        $existingProduct = $this->createStub(\App\Entity\Product::class);
        $this->productRepository->method('findBySku')->willReturn($existingProduct);
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $result = $this->makeService()->import($path, $this->makeJob(), $this->makeContext());

        $this->assertSame(0, $result->processed);
        $this->assertSame(1, $result->skipped);
    }

    public function testReturnsCorrectTotals(): void
    {
        $path = $this->writeCsv("name,sku,price\nGood,SKU-001,5.00\n,,\n");

        $this->productValidator->method('validate')->willReturn([]);
        $this->productRepository->method('findBySku')->willReturn(null);
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->makeService()->import($path, $this->makeJob(), $this->makeContext());

        $this->assertSame(2, $result->total());
        $this->assertSame(1, $result->processed);
        $this->assertSame(1, $result->skipped);
    }

    public function testDispatchesProductImportedEventForEachProduct(): void
    {
        $path = $this->writeCsv("name,sku,price\nWidget A,SKU-001,9.99\nWidget B,SKU-002,19.99\n");

        $this->productValidator->method('validate')->willReturn([]);
        $this->productRepository->method('findBySku')->willReturn(null);
        $this->entityManager->expects($this->exactly(2))->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        // Create mock for this test to verify event dispatch
        $mockDispatcher = $this->createMock(EventDispatcherInterface::class);
        $mockDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->isInstanceOf(ProductImportedEvent::class));

        $service = new ProductImportService(
            $this->managerRegistry,
            $this->productValidator,
            $this->productRepository,
            $this->reader,
            new NullLogger(),
            $this->importLogService,
            $mockDispatcher,
        );

        $result = $service->import($path, $this->makeJob(), $this->makeContext());

        $this->assertSame(2, $result->processed);
    }
}
