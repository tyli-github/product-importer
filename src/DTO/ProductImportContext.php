<?php

declare(strict_types=1);

namespace App\DTO;

final class ProductImportContext
{
    /**
     * @var array<string, true>
     */
    private array $processedSkus = [];

    public function __construct(
        private readonly bool $dryRun,
        private readonly bool $allowUpdates = false,
    ) {
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    public function isAllowUpdates(): bool
    {
        return $this->allowUpdates;
    }

    public function hasProcessedSku(string $sku): bool
    {
        return isset($this->processedSkus[$sku]);
    }

    public function markSkuAsProcessed(string $sku): void
    {
        $this->processedSkus[$sku] = true;
    }
}
