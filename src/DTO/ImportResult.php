<?php

declare(strict_types=1);

namespace App\DTO;

readonly class ImportResult
{
    public function __construct(
        public int $processed,
        public int $failed,
        public int $skipped,
        public int $updated = 0,
    ) {}

    public function total(): int
    {
        return $this->processed + $this->failed + $this->skipped + $this->updated;
    }
}
