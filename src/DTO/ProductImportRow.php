<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Product;

final readonly class ProductImportRow
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(private int $rowNumber, private array $raw, private Product $product) {}

    public function getRowNumber(): int
    {
        return $this->rowNumber;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getSku(): string
    {
        return $this->product->getSku() ?? '';
    }
}
