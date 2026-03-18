<?php

declare(strict_types=1);

namespace App\Service;

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
}
