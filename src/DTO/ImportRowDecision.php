<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Product;
use LogicException;

final class ImportRowDecision
{
    private const string TYPE_SKIP = 'skip';

    private const string TYPE_FAIL = 'fail';

    private const string TYPE_DRY_RUN_PROCESSED = 'dry_run_processed';

    private const string TYPE_BATCH = 'batch';

    private const string TYPE_UPDATE = 'update';

    private function __construct(private readonly string $type, private readonly ?Product $product = null) {}

    public static function skip(): self
    {
        return new self(self::TYPE_SKIP);
    }

    public static function fail(): self
    {
        return new self(self::TYPE_FAIL);
    }

    public static function dryRunProcessed(): self
    {
        return new self(self::TYPE_DRY_RUN_PROCESSED);
    }

    public static function batch(Product $product): self
    {
        return new self(self::TYPE_BATCH, $product);
    }

    public static function update(Product $product): self
    {
        return new self(self::TYPE_UPDATE, $product);
    }

    public function isSkip(): bool
    {
        return $this->type === self::TYPE_SKIP;
    }

    public function isFail(): bool
    {
        return $this->type === self::TYPE_FAIL;
    }

    public function isDryRunProcessed(): bool
    {
        return $this->type === self::TYPE_DRY_RUN_PROCESSED;
    }

    public function isBatch(): bool
    {
        return $this->type === self::TYPE_BATCH;
    }

    public function isUpdate(): bool
    {
        return $this->type === self::TYPE_UPDATE;
    }

    public function getProduct(): Product
    {
        if ($this->product === null) {
            throw new LogicException('No product is available for this import row decision.');
        }

        return $this->product;
    }
}
