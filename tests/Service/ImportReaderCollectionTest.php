<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Interface\ImportReaderInterface;
use App\Service\Reader\CsvImportReader;
use App\Service\Reader\HttpImportReader;
use App\Service\Reader\JsonImportReader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ImportReaderCollectionTest extends KernelTestCase
{
    /** @var ImportReaderInterface[] */
    private array $readers;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->readers = [
            $container->get(CsvImportReader::class),
            $container->get(JsonImportReader::class),
            $container->get(HttpImportReader::class),
        ];
    }

    public function testAllReadersImplementInterface(): void
    {
        foreach ($this->readers as $reader) {
            $this->assertInstanceOf(ImportReaderInterface::class, $reader);
        }
    }

    public function testCsvReaderSupportsCsvExtension(): void
    {
        $reader = $this->readers[0];
        $tmpFile = sys_get_temp_dir() . '/test_' . uniqid() . '.csv';
        file_put_contents($tmpFile, "name,sku\n");

        $this->assertTrue($reader->supports($tmpFile));
        $this->assertFalse($reader->supports('https://example.com/data'));
        $this->assertFalse($reader->supports('/tmp/file.json'));
    }

    public function testJsonReaderSupportsJsonExtension(): void
    {
        $reader = $this->readers[1];
        $tmpFile = sys_get_temp_dir() . '/test_' . uniqid() . '.json';
        file_put_contents($tmpFile, '[]');

        $this->assertTrue($reader->supports($tmpFile));
        $this->assertFalse($reader->supports('https://example.com/data'));
        $this->assertFalse($reader->supports('/tmp/file.csv'));
    }

    public function testHttpReaderSupportsHttpUrls(): void
    {
        $reader = $this->readers[2];

        $this->assertTrue($reader->supports('https://dummyjson.com/products'));
        $this->assertTrue($reader->supports('http://example.com/api/products'));
        $this->assertFalse($reader->supports('/var/share/products.csv'));
        $this->assertFalse($reader->supports('/var/share/products.json'));
    }

    public function testReadersDoNotOverlapOnCommonSources(): void
    {
        $csvFile = sys_get_temp_dir() . '/overlap_' . uniqid() . '.csv';
        $jsonFile = sys_get_temp_dir() . '/overlap_' . uniqid() . '.json';
        file_put_contents($csvFile, "name\n");
        file_put_contents($jsonFile, '[]');

        [$csv, $json, $http] = $this->readers;

        // Each source should be supported by exactly one reader
        $this->assertSame(1, (int) $csv->supports($csvFile) + (int) $json->supports($csvFile) + (int) $http->supports($csvFile));
        $this->assertSame(1, (int) $csv->supports($jsonFile) + (int) $json->supports($jsonFile) + (int) $http->supports($jsonFile));
    }
}
