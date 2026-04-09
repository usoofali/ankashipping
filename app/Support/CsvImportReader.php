<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class CsvImportReader
{
    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>}
     */
    public static function read(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Unable to open CSV file.');
        }

        $headers = fgetcsv($handle);
        if (! is_array($headers)) {
            fclose($handle);
            throw new RuntimeException('CSV file is missing header row.');
        }

        $normalizedHeaders = array_map(
            static fn (mixed $header): string => strtolower(trim((string) $header)),
            $headers
        );

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if (! is_array($line)) {
                continue;
            }

            $assoc = [];
            foreach ($normalizedHeaders as $index => $header) {
                $assoc[$header] = trim((string) ($line[$index] ?? ''));
            }

            if (count(array_filter($assoc, static fn (string $value): bool => $value !== '')) === 0) {
                continue;
            }

            $rows[] = $assoc;
        }

        fclose($handle);

        return [
            'headers' => $normalizedHeaders,
            'rows' => $rows,
        ];
    }
}
