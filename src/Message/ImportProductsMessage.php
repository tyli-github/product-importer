<?php

declare(strict_types=1);

namespace App\Message;

readonly class ImportProductsMessage
{
    public function __construct(
        public int $jobId,
        public string $source,
        public bool $dryRun = false,
        public bool $allowUpdates = false,
    ) {
    }
}
