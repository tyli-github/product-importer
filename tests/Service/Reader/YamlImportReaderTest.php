<?php

declare(strict_types=1);

namespace App\Tests\Service\Reader;

use App\Entity\ImportJob;
use App\Service\Reader\YamlImportReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class YamlImportReaderTest extends TestCase
{
    private YamlImportReader $reader;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->reader = new YamlImportReader();
        $this->tmpDir = sys_get_temp_dir();
    }

    private function writeYaml(string $content, string $extension = 'yaml'): string
    {
        $path = $this->tmpDir . '/products_' . uniqid() . '.' . $extension;
        file_put_contents($path, $content);

        return $path;
    }

    public function testSupportsYamlExtension(): void
    {
        $path = $this->writeYaml("- sku: X\n");
        $this->assertTrue($this->reader->supports($path));
    }

    public function testSupportsYmlExtension(): void
    {
        $path = $this->writeYaml("- sku: X\n", 'yml');
        $this->assertTrue($this->reader->supports($path));
    }

    public function testDoesNotSupportNonYamlExtension(): void
    {
        $this->assertFalse($this->reader->supports('/tmp/file.csv'));
        $this->assertFalse($this->reader->supports('/tmp/file.xml'));
    }

    public function testDoesNotSupportHttpUrl(): void
    {
        $this->assertFalse($this->reader->supports('https://example.com/products.yaml'));
    }

    public function testDoesNotSupportMissingFile(): void
    {
        $this->assertFalse($this->reader->supports('/tmp/nonexistent_' . uniqid() . '.yaml'));
    }

    public function testGetSourceType(): void
    {
        $this->assertSame(ImportJob::SOURCE_TYPE_YAML, $this->reader->getSourceType());
    }

    public function testReadsProductsFromFlatList(): void
    {
        $path = $this->writeYaml(<<<YAML
            - name: Widget A
              sku: SKU-YAML-001
              price: 9.99
              category: widgets
              stock: 10
              status: active
            - name: Widget B
              sku: SKU-YAML-002
              price: 19.99
              category: widgets
              stock: 5
              status: active
            YAML);

        $rows = iterator_to_array($this->reader->read($path));

        $this->assertCount(2, $rows);
        $this->assertSame('SKU-YAML-001', $rows[1]['sku']);
        $this->assertSame('Widget B', $rows[2]['name']);
    }

    public function testReadsProductsFromWrappedList(): void
    {
        $path = $this->writeYaml(<<<YAML
            products:
              - name: Widget C
                sku: SKU-YAML-003
                price: 5.00
                stock: 20
                status: active
            YAML);

        $rows = iterator_to_array($this->reader->read($path));

        $this->assertCount(1, $rows);
        $this->assertSame('SKU-YAML-003', $rows[1]['sku']);
    }

    public function testThrowsOnMissingFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/File not found/');

        iterator_to_array($this->reader->read('/tmp/nonexistent_' . uniqid() . '.yaml'));
    }

    public function testThrowsOnInvalidYaml(): void
    {
        $path = $this->writeYaml("key: [unclosed bracket\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid YAML/');

        iterator_to_array($this->reader->read($path));
    }

    public function testYieldsStringValues(): void
    {
        $path = $this->writeYaml(<<<YAML
            - name: Widget
              sku: SKU-YAML-004
              price: 5.00
              stock: 10
              status: active
            YAML);

        $rows = iterator_to_array($this->reader->read($path));
        foreach ($rows[1] as $value) {
            $this->assertIsString($value);
        }
    }
}
