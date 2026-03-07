<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\ImportJob;
use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\CsvReaderService;
use App\Service\ImportLogService;
use App\Service\ProductImportService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProductImportServiceTest extends TestCase
{
    private ManagerRegistry&Stub $managerRegistry;

    private EntityManagerInterface&MockObject $entityManager;

    private ValidatorInterface&Stub $validator;

    private ProductRepository&Stub $repository;

    private ImportLogService&Stub $importLogService;

    private CsvReaderService $csvReader;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->managerRegistry = $this->createStub(ManagerRegistry::class);
        $this->validator = $this->createStub(ValidatorInterface::class);
        $this->repository = $this->createStub(ProductRepository::class);
        $this->importLogService = $this->createStub(ImportLogService::class);
        $this->csvReader = new CsvReaderService(new NullLogger());
        $this->tmpDir = sys_get_temp_dir();

        $this->managerRegistry->method('getManager')->willReturn($this->entityManager);
        $this->entityManager->method('getRepository')->willReturn($this->repository);
    }

    private function makeService(): ProductImportService
    {
        return new ProductImportService(
            $this->managerRegistry,
            $this->validator,
            $this->csvReader,
            new NullLogger(),
            $this->importLogService,
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

    public function testImportsValidRowsSuccessfully(): void
    {
        $path = $this->writeCsv("name,sku,price\nWidget A,SKU-001,9.99\nWidget B,SKU-002,19.99\n");

        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->repository->method('findOneBy')->willReturn(null);
        $this->entityManager->expects($this->exactly(2))->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->makeService()->import($path, $this->makeJob());

        $this->assertSame(2, $result->processed);
        $this->assertSame(0, $result->failed);
        $this->assertSame(0, $result->skipped);
        $this->assertSame(2, $result->total());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCountsFailedRowsOnValidationError(): void
    {
        $path = $this->writeCsv("name,sku,price\n,INVALID,bad\n");

        $violation = new ConstraintViolation('This value should not be blank.', null, [], null, 'name', null);
        $violations = new ConstraintViolationList([$violation]);
        $this->validator->method('validate')->willReturn($violations);

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $result = $this->makeService()->import($path, $this->makeJob());

        $this->assertSame(0, $result->processed);
        $this->assertSame(1, $result->failed);
    }

    public function testDryRunDoesNotPersist(): void
    {
        $path = $this->writeCsv("name,sku,price\nWidget A,SKU-001,9.99\n");

        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->repository->method('findOneBy')->willReturn(null);
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $result = $this->makeService()->import($path, $this->makeJob(), dryRun: true);

        $this->assertSame(1, $result->processed);
    }

    public function testSkipsAllEmptyRows(): void
    {
        $path = $this->writeCsv("name,sku,price\n,,\n");

        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $result = $this->makeService()->import($path, $this->makeJob());

        $this->assertSame(0, $result->processed);
        $this->assertSame(1, $result->skipped);
    }

    public function testSkipsDuplicateSku(): void
    {
        $path = $this->writeCsv("name,sku,price\nWidget A,SKU-001,9.99\n");

        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $this->repository->method('findOneBy')->willReturn(new Product());

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $result = $this->makeService()->import($path, $this->makeJob());

        $this->assertSame(0, $result->processed);
        $this->assertSame(1, $result->skipped);
    }

    public function testReturnsCorrectTotals(): void
    {
        $path = $this->writeCsv("name,sku,price\nGood,SKU-001,5.00\n,,\n");

        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->repository->method('findOneBy')->willReturn(null);
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->makeService()->import($path, $this->makeJob());

        $this->assertSame(2, $result->total());
        $this->assertSame(1, $result->processed);
        $this->assertSame(1, $result->skipped);
    }
}
