<?php

declare(strict_types=1);

namespace App\Tests\Event;

use App\Entity\Product;
use App\Event\ProductImportedEvent;
use PHPUnit\Framework\TestCase;

class ProductImportedEventTest extends TestCase
{
    public function testProductImportedEventStoresProduct(): void
    {
        $product = new Product();
        $product->setName('Test GPU');
        $product->setSku('GPU-001');

        $event = new ProductImportedEvent($product);

        $this->assertSame($product, $event->getProduct());
    }
}
