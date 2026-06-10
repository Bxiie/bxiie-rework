<?php

declare(strict_types=1);

namespace App\Support\Csv;

use App\Http\Response;

/**
 * Builds CSV download responses.
 */
final class CsvResponse
{
    public static function download(string $filename, array $headers, array $rows): Response
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new \RuntimeException('Unable to create temporary CSV stream.');
        }

        fputcsv($handle, $headers, ',', '"', '\\');

        foreach ($rows as $row) {
            $csvRow = [];

            foreach ($headers as $header) {
                $csvRow[] = $row[$header] ?? '';
            }

            fputcsv($handle, $csvRow, ',', '"', '\\');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        if ($csv === false) {
            throw new \RuntimeException('Unable to read generated CSV.');
        }

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . self::safeFilename($filename) . '"',
        ]);
    }

    private static function safeFilename(string $filename): string
    {
        return preg_replace('/[^a-zA-Z0-9._-]/', '-', $filename) ?: 'export.csv';
    }
}

// End of file.
