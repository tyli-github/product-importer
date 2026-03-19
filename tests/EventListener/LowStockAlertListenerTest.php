<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\Product;
use App\Event\ProductImportedEvent;
use App\EventListener\LowStockAlertListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LowStockAlertListenerTest extends TestCase
{
    private LoggerInterface $logger;
    private LowStockAlertListener $listener;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->listener = new LowStockAlertListener($this->logger, 10);
    }

    public function testLogsWarningWhenStockBelowThreshold(): void
    {
        $product = new Product();
        $product->setName('GPU RTX 4090');
        $product->setSku('GPU-4090');
        $product->setStock(5);
        $product->setCategory('Graphics Cards');

        $event = new ProductImportedEvent($product);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Low stock detected after import',
                $this->callback(function (array $context) {
                    return $context['sku'] === 'GPU-4090'
                        && $context['stock'] === 5
                        && $context['threshold'] === 10;
                })
            );

        $this->listener->__invoke($event);
    }

    public function testDoesNotLogWhenStockAboveThreshold(): void
    {
        $product = new Product();
        $product->setName('GPU RTX 4090');
        $product->setSku('GPU-4090');
        $product->setStock(15);

        $event = new ProductImportedEvent($product);

        $this->logger->expects($this->never())
            ->method('warning');

        $this->listener->__invoke($event);
    }

    public function testDoesNotLogWhenStockIsNull(): void
    {
        $product = new Product();
        $product->setName('GPU RTX 4090');
        $product->setSku('GPU-4090');
        $product->setStock(null);

        $event = new ProductImportedEvent($product);

        $this->logger->expects($this->never())
            ->method('warning');

        $this->listener->__invoke($event);
    }

    public function testLogsWarningWhenStockEqualsThreshold(): void
    {
        $product = new Product();
        $product->setName('CPU AMD Ryzen');
        $product->setSku('CPU-RYZEN');
        $product->setStock(10);

        $event = new ProductImportedEvent($product);

        // Threshold is 10, stock is 10, so NOT < 10 → no warning
        $this->logger->expects($this->never())
            ->method('warning');

        $this->listener->__invoke($event);
    }

    public function testLogsWarningWithConfigurableThreshold(): void
    {
        $listener = new LowStockAlertListener($this->logger, 5);

        $product = new Product();
        $product->setName('SSD Samsung');
        $product->setSku('SSD-980');
        $product->setStock(3);

        $event = new ProductImportedEvent($product);

        $this->logger->expects($this->once())
            ->method('warning');

        $listener->__invoke($event);
    }
}
