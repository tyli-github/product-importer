<?php

declare(strict_types=1);

namespace App\Service;

use App\Interface\ImportReaderInterface;

final class ImportSourceDetector
{
    public static function isHttpUrl(string $source): bool
    {
        $parts = parse_url($source);

        if ($parts === false) {
            return false;
        }

        return isset($parts['scheme'], $parts['host'])
            && in_array(strtolower($parts['scheme']), ['http', 'https'], true);
    }

    public function detect(string $source, iterable $readers): ?ImportReaderInterface
    {
        /** @var ImportReaderInterface $reader */
        foreach ($readers as $reader) {
            if ($reader->supports($source)) {
                return $reader;
            }
        }

        return null;
    }
}
