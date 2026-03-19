<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Event\ProductImportedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ProductImportedEvent::class)]
readonly class LowStockAlertListener
{
    public function __construct(
        private LoggerInterface $logger,
        private int $lowStockThreshold,
    ) {
    }

    public function __invoke(ProductImportedEvent $event): void
    {
        $product = $event->getProduct();

        if ($product->getStock() !== null && $product->getStock() < $this->lowStockThreshold) {
            $this->logger->warning('Low stock detected after import', [
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'stock' => $product->getStock(),
                'category' => $product->getCategory(),
                'threshold' => $this->lowStockThreshold,
            ]);
        }
    }
}
