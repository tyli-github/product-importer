<?php

declare(strict_types=1);

namespace App\Interface;

use Generator;

interface ImportReaderInterface
{
    /**
     * @return Generator<int, array<string, string>>
     */
    public function read(string $source): Generator;

    public function supports(string $source): bool;

    public function getSourceType(): string;
}
