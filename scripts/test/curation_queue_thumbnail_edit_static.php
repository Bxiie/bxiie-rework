<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$repo = file_get_contents($root . '/app/Tenant/Curation/CurationRepository.php') ?: '';
$controller = file_get_contents($root . '/app/Http/Controllers/Tenant/CurationController.php') ?: '';

$required = [
    'm.uuid primary_media_uuid',
    'LEFT JOIN media_assets m ON m.id=a.primary_media_id',
    'private function curationThumbnail(array $item, string $title): string',
    '/admin/media?uuid=',
    '/admin/artworks/edit?id=',
    'Edit artwork',
    "rawurlencode('/admin/curation')",
];

$missing = [];
foreach ($required as $needle) {
    if (!str_contains($repo . "\n" . $controller, $needle)) {
        $missing[] = $needle;
    }
}

if ($missing !== []) {
    fwrite(STDERR, "[FAIL] Curation queue thumbnail/edit static check failed:\n");
    foreach ($missing as $needle) {
        fwrite(STDERR, "[FAIL]  - Missing marker: {$needle}\n");
    }
    exit(1);
}

echo "Curation queue thumbnail/edit static checks passed.\n";

// End of file.
