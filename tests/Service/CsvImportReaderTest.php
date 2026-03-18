<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Exception\CsvReaderException;
use App\Service\Reader\CsvImportReader;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CsvImportReaderTest extends TestCase
{
    private CsvImportReader $reader;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->reader = new CsvImportReader(new NullLogger());
        $this->tmpDir = sys_get_temp_dir();
    }

    private function writeCsv(string $content): string
    {
        $path = $this->tmpDir . '/test_' . uniqid() . '.csv';
        file_put_contents($path, $content);

        return $path;
    }

    public function testReadsRowsAsAssociativeArrays(): void
    {
        $path = $this->writeCsv("name,sku,price\nWidget A,SKU-001,9.99\nWidget B,SKU-002,19.99\n");
        $rows = iterator_to_array($this->reader->read($path));

        $this->assertCount(2, $rows);
        $this->assertSame(['name' => 'Widget A', 'sku' => 'SKU-001', 'price' => '9.99'], $rows[2]);
        $this->assertSame(['name' => 'Widget B', 'sku' => 'SKU-002', 'price' => '19.99'], $rows[3]);
    }

    public function testNormalizesHeaders(): void
    {
        $path = $this->writeCsv("  Name , SKU , Price \nWidget,SKU-001,5.00\n");

        $rows = iterator_to_array($this->reader->read($path));

        $this->assertArrayHasKey('name', $rows[2]);
        $this->assertArrayHasKey('sku', $rows[2]);
        $this->assertArrayHasKey('price', $rows[2]);
    }

    public function testThrowsOnMissingFile(): void
    {
        $this->expectException(CsvReaderException::class);
        $this->expectExceptionMessageMatches('/File not found/');

        iterator_to_array($this->reader->read('/nonexistent/path/file.csv'));
    }

    public function testThrowsOnEmptyFile(): void
    {
        $path = $this->writeCsv('');

        $this->expectException(CsvReaderException::class);
        $this->expectExceptionMessageMatches('/Empty or unreadable/');

        iterator_to_array($this->reader->read($path));
    }

    public function testSkipsRowsWithColumnCountMismatch(): void
    {
        $path = $this->writeCsv("name,sku,price\nBad Row,SKU-BAD\nGood Row,SKU-001,5.00\n");

        $rows = iterator_to_array($this->reader->read($path));

        $this->assertCount(1, $rows);
        $this->assertSame('Good Row', $rows[3]['name']);
    }

    public function testHeaderOnlyFileYieldsNoRows(): void
    {
        $path = $this->writeCsv("name,sku,price\n");

        $rows = iterator_to_array($this->reader->read($path));

        $this->assertCount(0, $rows);
    }

    public function testYieldKeyIsRowNumber(): void
    {
        $path = $this->writeCsv("name,sku\nFirst,SKU-001\nSecond,SKU-002\n");

        $rows = iterator_to_array($this->reader->read($path));

        $this->assertArrayHasKey(2, $rows);
        $this->assertArrayHasKey(3, $rows);
    }

    public function testSupportsCsvByExtension(): void
    {
        $path = $this->writeCsv("name,sku\n");

        $this->assertTrue($this->reader->supports($path));
    }

    public function testDoesNotSupportJsonExtension(): void
    {
        $path = $this->tmpDir . '/test_' . uniqid() . '.json';
        file_put_contents($path, '[]');

        $this->assertFalse($this->reader->supports($path));
    }

    public function testDoesNotSupportHttpUrlEvenWithCsvExtension(): void
    {
        $this->assertFalse($this->reader->supports('https://example.com/products.csv'));
        $this->assertFalse($this->reader->supports('http://example.com/products.csv'));
    }
}
