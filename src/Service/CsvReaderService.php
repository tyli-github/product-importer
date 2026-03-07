<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\CsvReaderException;
use Generator;
use Psr\Log\LoggerInterface;

readonly class CsvReaderService
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    /**
     * Yields each data row as an associative array keyed by header columns.
     *
     * @return Generator<int, array<string, string>>
     * @throws CsvReaderException
     */
    public function read(string $filePath): Generator
    {
        if (!file_exists($filePath)) {
            throw new CsvReaderException(sprintf('File not found: %s', $filePath));
        }

        if (!is_readable($filePath)) {
            throw new CsvReaderException(sprintf('File not readable: %s', $filePath));
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new CsvReaderException(sprintf('Failed to open file: %s', $filePath));
        }

        try {
            $headers = fgetcsv($handle, escape: '');

            if ($headers === false) {
                throw new CsvReaderException(sprintf('Empty or unreadable CSV file: %s', $filePath));
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
