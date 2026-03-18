<?php

declare(strict_types=1);

namespace App\Service\Reader;

use App\Entity\ImportJob;
use App\Exception\CsvReaderException;
use App\Interface\ImportReaderInterface;
use App\Service\ImportSourceDetector;
use Generator;
use Psr\Log\LoggerInterface;

readonly class CsvImportReader implements ImportReaderInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function supports(string $source): bool
    {
        // if a source is url, skip this reader
        if (ImportSourceDetector::isHttpUrl($source) === true) {
            return false;
        }

        // simple extension check: continue with file exists and mime check
        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            return false;
        }

        if (file_exists($source) === false) {
            return false;
        }

        $mime = mime_content_type($source);

        return $mime === 'text/csv' || $mime === 'text/plain';
    }

    public function getSourceType(): string
    {
        return ImportJob::SOURCE_TYPE_CSV;
    }

    /**
     * @return Generator<int, array<string, string>>
     * @throws CsvReaderException
     */
    public function read(string $source): Generator
    {
        if (!file_exists($source)) {
            throw new CsvReaderException(sprintf('File not found: %s', $source));
        }

        if (!is_readable($source)) {
            throw new CsvReaderException(sprintf('File not readable: %s', $source));
        }

        $handle = fopen($source, 'r');
        if ($handle === false) {
            throw new CsvReaderException(sprintf('Failed to open file: %s', $source));
        }

        try {
            $headers = fgetcsv($handle, escape: '');

            if ($headers === false) {
                throw new CsvReaderException(sprintf('Empty or unreadable CSV file: %s', $source));
            }

            $headers = array_map(static fn(string $header): string => strtolower(trim($header)), $headers);

            $rowNumber = 1;
            while (($row = fgetcsv($handle, escape: '')) !== false) {
                $rowNumber++;

                if (count($row) !== count($headers)) {
                    $this->logger->warning('Skipping row with column count mismatch', [
                        'row_number' => $rowNumber,
                        'expected' => count($headers),
                        'actual' => count($row),
                    ]);
                    continue;
                }

                yield $rowNumber => array_combine($headers, $row);
            }
        } finally {
            fclose($handle);
        }
    }
}
