<?php

declare(strict_types=1);

namespace App\Tests\Service\Reader;

use App\Entity\ImportJob;
use App\Service\Reader\XmlImportReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class XmlImportReaderTest extends TestCase
{
    private XmlImportReader $reader;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->reader = new XmlImportReader();
        $this->tmpDir = sys_get_temp_dir();
    }

    private function writeXml(string $content): string
    {
        $path = $this->tmpDir . '/products_' . uniqid() . '.xml';
        file_put_contents($path, $content);

        return $path;
    }

    public function testSupportsXmlFile(): void
    {
        $path = $this->writeXml('<?xml version="1.0"?><products><product><sku>X</sku></product></products>');
        $this->assertTrue($this->reader->supports($path));
    }

    public function testDoesNotSupportNonXmlExtension(): void
    {
        $this->assertFalse($this->reader->supports('/tmp/file.csv'));
        $this->assertFalse($this->reader->supports('/tmp/file.json'));
    }

    public function testDoesNotSupportHttpUrl(): void
    {
        $this->assertFalse($this->reader->supports('https://example.com/products.xml'));
    }

    public function testDoesNotSupportMissingFile(): void
    {
        $this->assertFalse($this->reader->supports('/tmp/nonexistent_' . uniqid() . '.xml'));
    }

    public function testGetSourceType(): void
    {
        $this->assertSame(ImportJob::SOURCE_TYPE_XML, $this->reader->getSourceType());
    }

    public function testReadsProductsFromValidXml(): void
    {
        $path = $this->writeXml(<<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <products>
                <product>
                    <name>Widget A</name>
                    <sku>SKU-XML-001</sku>
                    <price>9.99</price>
                    <category>widgets</category>
                    <stock>10</stock>
                    <status>active</status>
                </product>
                <product>
                    <name>Widget B</name>
                    <sku>SKU-XML-002</sku>
                    <price>19.99</price>
                    <category>widgets</category>
                    <stock>5</stock>
                    <status>active</status>
                </product>
            </products>
            XML);

        $rows = iterator_to_array($this->reader->read($path));

        $this->assertCount(2, $rows);
        $this->assertSame('SKU-XML-001', $rows[1]['sku']);
        $this->assertSame('Widget B', $rows[2]['name']);
        $this->assertSame('19.99', $rows[2]['price']);
    }

    public function testThrowsOnMissingFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/File not found/');

        iterator_to_array($this->reader->read('/tmp/nonexistent_' . uniqid() . '.xml'));
    }

    public function testThrowsOnInvalidXml(): void
    {
        $path = $this->writeXml('this is not xml <<>>');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid XML/');

        iterator_to_array($this->reader->read($path));
    }

    public function testYieldsStringValues(): void
    {
        $path = $this->writeXml(<<<XML
            <?xml version="1.0"?>
            <products>
                <product>
                    <name>Widget</name>
                    <sku>SKU-XML-003</sku>
                    <price>5.00</price>
                    <stock>10</stock>
                    <status>active</status>
                </product>
            </products>
            XML);

        $rows = iterator_to_array($this->reader->read($path));
        foreach ($rows[1] as $value) {
            $this->assertIsString($value);
        }
    }
}
