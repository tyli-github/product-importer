<?php

declare(strict_types=1);

namespace App\Service\Reader;

use App\Entity\ImportJob;
use App\Interface\ImportReaderInterface;
use App\Service\ImportSourceDetector;
use Generator;
use RuntimeException;

readonly class XmlImportReader implements ImportReaderInterface
{
    public function supports(string $source): bool
    {
        if (ImportSourceDetector::isHttpUrl($source) === true) {
            return false;
        }

        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        if ($extension !== 'xml') {
            return false;
        }

        if (file_exists($source) === false) {
            return false;
        }

        $mime = mime_content_type($source);

        return $mime === 'text/xml' || $mime === 'application/xml';
    }

    public function getSourceType(): string
    {
        return ImportJob::SOURCE_TYPE_XML;
    }

    /**
     * @return Generator<int, array<string, string>>
     */
    public function read(string $source): Generator
    {
        if (!file_exists($source)) {
            throw new RuntimeException(sprintf('File not found: %s', $source));
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($source);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        if ($xml === false) {
            throw new RuntimeException(sprintf('Invalid XML in file: %s', $source));
        }

        $rowNumber = 0;
        foreach ($xml->product as $product) {
            $rowNumber++;
            $row = [];
            foreach ($product->children() as $field) {
                $row[$field->getName()] = (string) $field;
            }

            yield $rowNumber => $row;
        }
    }
}
