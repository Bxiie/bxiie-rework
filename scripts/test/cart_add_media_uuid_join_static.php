<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];

$repoPath = $root . '/app/Tenant/Sales/SalesRepository.php';
$repo = file_get_contents($repoPath) ?: '';

foreach ([
    'm.uuid AS media_uuid',
    'LEFT JOIN media_assets m ON m.id = a.primary_media_id',
    'media_uuid_snapshot',
    'artworkForPurchase',
] as $needle) {
    if (!str_contains($repo, $needle)) {
        $failures[] = "SalesRepository missing {$needle}";
    }
}

if (str_contains($repo, 'a.media_uuid')) {
    $failures[] = 'SalesRepository still selects nonexistent artworks.media_uuid.';
}

if ($failures !== []) {
    fwrite(STDERR, "Cart add media UUID join static checks failed:
");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}
");
    }
    exit(1);
}

fwrite(STDOUT, "Cart add media UUID join static checks passed.
");

// End of file.
