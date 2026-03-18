<?php

declare(strict_types=1);

namespace App\Service\Reader;

use App\Entity\ImportJob;
use App\Interface\ImportReaderInterface;
use App\Service\ImportSourceDetector;
use Generator;
use RuntimeException;

readonly class JsonImportReader implements ImportReaderInterface
{
    public function supports(string $source): bool
    {
        // if a source is url, skip this reader
        if (ImportSourceDetector::isHttpUrl($source) === true) {
            return false;
        }

        // simple extension check: continue with file exists and mime check
        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        if ($extension !== 'json') {
            return false;
        }

        if (file_exists($source) === false) {
            return false;
        }

        return mime_content_type($source) === 'application/json';
    }

    public function getSourceType(): string
    {
        return ImportJob::SOURCE_TYPE_JSON;
    }

    /**
     * @return Generator<int, array<string, string>>
     */
    public function read(string $source): Generator
    {
        if (!file_exists($source)) {
            throw new RuntimeException(sprintf('File not found: %s', $source));
        }

        $content = file_get_contents($source);
        if ($content === false) {
            throw new RuntimeException(sprintf('Failed to read file: %s', $source));
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new RuntimeException(sprintf('Invalid JSON in file: %s', $source));
        }

        // Support both flat array and wrapped array (e.g. {"products": [...]})
        $rows = array_is_list($data) ? $data : (array_values(array_filter($data, 'is_array'))[0] ?? []);

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            yield ($index + 1) => array_map(static fn($v): string => (string) $v, $row);
        }
    }
}
