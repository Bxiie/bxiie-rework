<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents(
    $root . '/app/Tenant/Media/WatermarkService.php'
);

$failures = [];

foreach ([
    'FROM media_assets',
    'WHERE tenant_id = :tenant_id',
    'AND uuid = :media_uuid',
    '$candidates = [',
    "'/storage/'",
    "'/public/'",
    'watermark media UUID not found',
    'watermark image file missing',
] as $marker) {
    if (!str_contains($service, $marker)) {
        $failures[] = "WatermarkService missing marker: {$marker}";
    }
}

foreach ([
    'INNER JOIN artworks',
    "atype.code = 'site_images'",
    'm.is_private = 0',
] as $stale) {
    if (str_contains($service, $stale)) {
        $failures[] = "Overly restrictive watermark lookup remains: {$stale}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Watermark image lookup check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Watermark image lookup uses the saved tenant media UUID directly.\n";

// End of file.
