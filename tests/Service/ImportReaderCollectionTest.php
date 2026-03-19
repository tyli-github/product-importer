<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Interface\ImportReaderInterface;
use App\Service\Reader\CsvImportReader;
use App\Service\Reader\HttpImportReader;
use App\Service\Reader\JsonImportReader;
use App\Service\Reader\XmlImportReader;
use App\Service\Reader\YamlImportReader;
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
            $container->get(XmlImportReader::class),
            $container->get(YamlImportReader::class),
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

    public function testXmlReaderSupportsXmlExtension(): void
    {
        $reader = $this->readers[3];
        $tmpFile = sys_get_temp_dir() . '/test_' . uniqid() . '.xml';
        file_put_contents($tmpFile, '<?xml version="1.0"?><products/>');

        $this->assertTrue($reader->supports($tmpFile));
        $this->assertFalse($reader->supports('https://example.com/data.xml'));
        $this->assertFalse($reader->supports('/tmp/file.csv'));
    }

    public function testYamlReaderSupportsYamlAndYmlExtensions(): void
    {
        $reader = $this->readers[4];
        $yamlFile = sys_get_temp_dir() . '/test_' . uniqid() . '.yaml';
        $ymlFile = sys_get_temp_dir() . '/test_' . uniqid() . '.yml';
        file_put_contents($yamlFile, "- sku: X\n");
        file_put_contents($ymlFile, "- sku: Y\n");

        $this->assertTrue($reader->supports($yamlFile));
        $this->assertTrue($reader->supports($ymlFile));
        $this->assertFalse($reader->supports('https://example.com/data.yaml'));
        $this->assertFalse($reader->supports('/tmp/file.csv'));
    }

    public function testReadersDoNotOverlapOnCommonSources(): void
    {
        $csvFile = sys_get_temp_dir() . '/overlap_' . uniqid() . '.csv';
        $jsonFile = sys_get_temp_dir() . '/overlap_' . uniqid() . '.json';
        $xmlFile = sys_get_temp_dir() . '/overlap_' . uniqid() . '.xml';
        $yamlFile = sys_get_temp_dir() . '/overlap_' . uniqid() . '.yaml';
        file_put_contents($csvFile, "name\n");
        file_put_contents($jsonFile, '[]');
        file_put_contents($xmlFile, '<?xml version="1.0"?><products/>');
        file_put_contents($yamlFile, "- sku: X\n");

        $readers = $this->readers;
        $supportCounts = static fn(string $file): int => array_sum(
            array_map(static fn($reader): int => (int) $reader->supports($file), $readers)
        );

        // Each source should be supported by exactly one reader
        $this->assertSame(1, $supportCounts($csvFile));
        $this->assertSame(1, $supportCounts($jsonFile));
        $this->assertSame(1, $supportCounts($xmlFile));
        $this->assertSame(1, $supportCounts($yamlFile));
    }
}
