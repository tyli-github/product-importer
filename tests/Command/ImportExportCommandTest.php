<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ImportExportCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private EntityManagerInterface $em;
    private string $tmpDir;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->em->getConnection()->executeStatement(
            'TRUNCATE TABLE import_log, product, import_job RESTART IDENTITY CASCADE'
        );

        $this->tmpDir = sys_get_temp_dir() . '/export_cmd_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);

        $command = new Application(self::$kernel)->find('import:export');
        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmpDir . '/*') ?: []);
        rmdir($this->tmpDir);
    }

    private function createProduct(string $name, string $sku, string $category = 'widgets', string $status = 'active'): void
    {
        $product = (new Product())
            ->setName($name)
            ->setSku($sku)
            ->setCategory($category)
            ->setStatus($status)
            ->setPrice('9.99');

        $this->em->persist($product);
        $this->em->flush();
    }

    public function testExportCsvCreatesFile(): void
    {
        $this->createProduct('Widget A', 'SKU-001');

        $outputPath = $this->tmpDir . '/out.csv';
        $code = $this->commandTester->execute(['--format' => 'csv', '--output' => $outputPath]);

        $this->assertSame(0, $code);
        $this->assertFileExists($outputPath);
        $this->assertStringContainsString('SKU-001', file_get_contents($outputPath));
        $this->assertStringContainsString('Exported 1 product', $this->commandTester->getDisplay());
    }

    public function testExportJsonCreatesFile(): void
    {
        $this->createProduct('Gadget X', 'SKU-002');

        $outputPath = $this->tmpDir . '/out.json';
        $code = $this->commandTester->execute(['--format' => 'json', '--output' => $outputPath]);

        $this->assertSame(0, $code);
        $this->assertFileExists($outputPath);

        $data = json_decode(file_get_contents($outputPath), true);
        $this->assertCount(1, $data);
        $this->assertSame('SKU-002', $data[0]['sku']);
    }

    public function testExportFiltersByCategory(): void
    {
        $this->createProduct('Widget A', 'SKU-001', 'widgets');
        $this->createProduct('Gadget X', 'SKU-002', 'gadgets');

        $outputPath = $this->tmpDir . '/filtered.csv';
        $code = $this->commandTester->execute([
            '--format'   => 'csv',
            '--output'   => $outputPath,
            '--category' => 'widgets',
        ]);

        $this->assertSame(0, $code);
        $content = file_get_contents($outputPath);
        $this->assertStringContainsString('SKU-001', $content);
        $this->assertStringNotContainsString('SKU-002', $content);
    }

    public function testExportFiltersByStatus(): void
    {
        $this->createProduct('Active Widget', 'SKU-003', 'widgets', 'active');
        $this->createProduct('Inactive Widget', 'SKU-004', 'widgets', 'inactive');

        $outputPath = $this->tmpDir . '/status.csv';
        $code = $this->commandTester->execute([
            '--format' => 'csv',
            '--output' => $outputPath,
            '--status' => 'inactive',
        ]);

        $this->assertSame(0, $code);
        $content = file_get_contents($outputPath);
        $this->assertStringContainsString('SKU-004', $content);
        $this->assertStringNotContainsString('SKU-003', $content);
    }

    public function testInvalidFormatReturnsFailure(): void
    {
        $code = $this->commandTester->execute(['--format' => 'xml']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Invalid format', $this->commandTester->getDisplay());
    }

    public function testExportWithNoProductsSucceeds(): void
    {
        $outputPath = $this->tmpDir . '/empty.csv';
        $code = $this->commandTester->execute(['--format' => 'csv', '--output' => $outputPath]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Exported 0 product', $this->commandTester->getDisplay());
    }
}
