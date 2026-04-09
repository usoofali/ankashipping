<?php

declare(strict_types=1);

namespace App\Support;

final class VinNormalizer
{
    public static function normalize(string $vin): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($vin)) ?? '');
    }

    public static function isValidFormat(string $normalizedVin): bool
    {
        if (strlen($normalizedVin) !== 17) {
            return false;
        }

        if (! ctype_alnum($normalizedVin)) {
            return false;
        }

        return ! preg_match('/[IOQ]/', $normalizedVin);
    }
}
