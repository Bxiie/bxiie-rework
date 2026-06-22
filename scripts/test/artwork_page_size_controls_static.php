<?php

declare(strict_types=1);

use App\Support\Pagination\Pagination;

$root = dirname(__DIR__, 2);
require_once $root . '/app/Support/Pagination/Pagination.php';

$standard = Pagination::standardPageSizes();
if ($standard !== [10, 20, 30, 40, 50, 60, 70, 80, 90, 100]) {
    throw new RuntimeException('Standard artwork page sizes must run from 10 through 100 in steps of 10.');
}

if (Pagination::allowedLimitFromQuery('100', 50, $standard) !== 100) {
    throw new RuntimeException('Expected supported page size 100.');
}
if (Pagination::allowedLimitFromQuery('5000', 50, $standard) !== 50) {
    throw new RuntimeException('Unsupported page sizes must fall back to the default.');
}
if (Pagination::allowedLimitFromQuery('24', 24, [10, 20, 24, 30]) !== 24) {
    throw new RuntimeException('Public portfolio default page size 24 must remain selectable.');
}

$checks = [
    'app/Http/Controllers/Tenant/HomeController.php' => [
        "\$_GET['per_page'] ?? null",
        '[10, 20, 24, 30, 40, 50, 60, 70, 80, 90, 100]',
        '24 (default)',
        'Artworks per page',
        "'per_page' => \$pageSize",
    ],
    'app/Http/Controllers/Tenant/Admin/ArtworksController.php' => [
        "\$_GET['per_page'] ?? null",
        'Pagination::standardPageSizes()',
        '50 (default)',
        'Artworks per page',
        "'per_page' => \$pageSize",
    ],
    'app/Http/Controllers/Tenant/Admin/ArtworkPlacementController.php' => [
        "\$_GET['per_page'] ?? null",
        'Pagination::standardPageSizes()',
        '50 (default)',
        'Artworks per page',
        "'per_page' => \$pageSize",
    ],
];

foreach ($checks as $relative => $needles) {
    $text = file_get_contents($root . '/' . $relative);
    if ($text === false) {
        throw new RuntimeException("Missing {$relative}");
    }
    foreach ($needles as $needle) {
        if (!str_contains($text, $needle)) {
            throw new RuntimeException("Missing {$needle} in {$relative}");
        }
    }
}

echo "Artwork page-size control static checks passed.\n";

// End of file.
