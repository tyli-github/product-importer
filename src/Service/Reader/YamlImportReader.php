<?php

declare(strict_types=1);

namespace App\Service\Reader;

use App\Entity\ImportJob;
use App\Interface\ImportReaderInterface;
use App\Service\ImportSourceDetector;
use Generator;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

readonly class YamlImportReader implements ImportReaderInterface
{
    public function supports(string $source): bool
    {
        if (ImportSourceDetector::isHttpUrl($source) === true) {
            return false;
        }

        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        if (!in_array($extension, ['yaml', 'yml'], true)) {
            return false;
        }

        return file_exists($source);
    }

    public function getSourceType(): string
    {
        return ImportJob::SOURCE_TYPE_YAML;
    }

    /**
     * @return Generator<int, array<string, string>>
     */
    public function read(string $source): Generator
    {
        if (!file_exists($source)) {
            throw new RuntimeException(sprintf('File not found: %s', $source));
        }

        try {
            $data = Yaml::parseFile($source);
        } catch (ParseException $exception) {
            throw new RuntimeException(sprintf('Invalid YAML in file: %s — %s', $source, $exception->getMessage()));
        }

        if (!is_array($data)) {
            throw new RuntimeException(sprintf('YAML file must contain a list of products: %s', $source));
        }

        // Support both flat list and wrapped (e.g. {products: [...]})
        $rows = array_is_list($data) ? $data : (array_values(array_filter($data, 'is_array'))[0] ?? []);

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            yield ($index + 1) => array_map(static fn($value): string => (string) $value, $row);
        }
    }
}
