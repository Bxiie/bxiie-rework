<?php

declare(strict_types=1);

/**
 * Manual verification script for CSV response generation.
 */

use App\Support\Csv\CsvResponse;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$response = CsvResponse::download(
    filename: 'test export.csv',
    headers: ['id', 'name'],
    rows: [
        ['id' => '1', 'name' => 'Alpha'],
        ['id' => '2', 'name' => 'Beta, With Comma'],
    ],
);

ob_start();
$response->send();
$body = ob_get_clean();

if (!str_contains($body, 'Beta, With Comma')) {
    fwrite(STDERR, "CSV body did not contain expected value.\n");
    exit(1);
}

echo $body;

// End of file.
