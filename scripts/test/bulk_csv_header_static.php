<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Admin\ArtworkBulkUploadController;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$path = tempnam(sys_get_temp_dir(), 'artsfolio-bulk-csv-');
if ($path === false) {
    fwrite(STDERR, "Unable to create temporary CSV.\n");
    exit(1);
}

try {
    file_put_contents($path, "\xEF\xBB\xBFfilename,title\nexample.jpg,Example\n");
    $controller = (new ReflectionClass(ArtworkBulkUploadController::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(ArtworkBulkUploadController::class, 'csv');
    [$headers, $rows] = $method->invoke($controller, $path);

    if ($headers !== ['filename', 'title'] || ($rows[0]['filename'] ?? null) !== 'example.jpg' || ($rows[0]['title'] ?? null) !== 'Example') {
        fwrite(STDERR, "Bulk CSV UTF-8 BOM header normalization failed.\n");
        exit(1);
    }
} finally {
    @unlink($path);
}

echo "Bulk CSV header normalization checks passed.\n";

// End of file.
