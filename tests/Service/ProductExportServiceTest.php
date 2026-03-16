<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\ProductExportService;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ProductExportServiceTest extends TestCase
{
    private ProductRepository&Stub $repository;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(ProductRepository::class);
        $this->tmpDir = sys_get_temp_dir() . '/export_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmpDir . '/*') ?: []);
        rmdir($this->tmpDir);
    }

    private function makeService(): ProductExportService
    {
        return new ProductExportService($this->repository, new NullLogger());
    }

    private function makeProduct(int $id, string $name, string $sku): Product
    {
        $product = new Product();
        $product->setName($name);
        $product->setSku($sku);
        $product->setPrice('9.99');
        $product->setCategory('widgets');
        $product->setStatus('active');
        $product->setStock(10);

        // Set id via reflection
        $ref = new \ReflectionProperty(Product::class, 'id');
        $ref->setValue($product, $id);

        return $product;
    }

    private function stubQueryBuilder(array $products): void
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn($products);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->repository->method('createQueryBuilder')->willReturn($qb);
    }

    public function testExportCsvWritesHeaderAndRows(): void
    {
        $products = [
            $this->makeProduct(1, 'Widget A', 'SKU-001'),
            $this->makeProduct(2, 'Widget B', 'SKU-002'),
        ];
        $this->stubQueryBuilder($products);

        $path = $this->tmpDir . '/products.csv';
        $count = $this->makeService()->exportCsv($path);

        $this->assertSame(2, $count);
        $this->assertFileExists($path);

        $rows = array_map(fn($line) => str_getcsv($line, escape: ''), array_filter(explode("\n", file_get_contents($path))));
        $this->assertSame(['id', 'name', 'sku', 'price', 'description', 'category', 'stock', 'status', 'createdAt', 'updatedAt'], $rows[0]);
        $this->assertSame('SKU-001', $rows[1][2]);
        $this->assertSame('SKU-002', $rows[2][2]);
    }

    public function testExportCsvReturnsZeroForEmptyResult(): void
    {
        $this->stubQueryBuilder([]);

        $count = $this->makeService()->exportCsv($this->tmpDir . '/empty.csv');

        $this->assertSame(0, $count);
    }

    public function testExportJsonWritesFlatArray(): void
    {
        $products = [$this->makeProduct(1, 'Widget A', 'SKU-001')];
        $this->stubQueryBuilder($products);

        $path = $this->tmpDir . '/products.json';
        $count = $this->makeService()->exportJson($path);

        $this->assertSame(1, $count);
        $this->assertFileExists($path);

        $data = json_decode(file_get_contents($path), true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertSame('SKU-001', $data[0]['sku']);
        $this->assertSame('Widget A', $data[0]['name']);
        $this->assertArrayHasKey('createdAt', $data[0]);
    }

    public function testExportJsonReturnsZeroForEmptyResult(): void
    {
        $this->stubQueryBuilder([]);

        $path = $this->tmpDir . '/empty.json';
        $count = $this->makeService()->exportJson($path);

        $this->assertSame(0, $count);
        $this->assertSame('[]', trim(file_get_contents($path)));
    }

    public function testExportCreatesOutputDirectoryIfMissing(): void
    {
        $this->stubQueryBuilder([]);

        $nestedPath = $this->tmpDir . '/nested/deep/products.csv';
        $this->makeService()->exportCsv($nestedPath);

        $this->assertFileExists($nestedPath);
    }
}
