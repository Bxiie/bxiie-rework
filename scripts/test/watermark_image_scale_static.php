<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents(
    $root . '/app/Tenant/Media/WatermarkService.php'
);
$settings = (string) file_get_contents(
    $root . '/app/Http/Controllers/Tenant/Admin/SettingsController.php'
);

$failures = [];

foreach ([
    '$widthScale = match ($sizeChoice)',
    '1 => 0.20',
    '2 => 0.30',
    '3 => 0.40',
    '4 => 0.50',
    'default => 0.60',
    '$maxHeight = max(24, (int) round($targetHeight * 0.45))',
    '4.0',
] as $marker) {
    if (!str_contains($service, $marker)) {
        $failures[] = "WatermarkService missing marker: {$marker}";
    }
}

if (str_contains(
    $service,
    "max(24, (int) round(\$targetWidth * \$scale))"
)) {
    $failures[] = 'Legacy undersized watermark scaling remains.';
}

if (!str_contains($settings, 'image width: 20%–60%')) {
    $failures[] = 'Watermark size guidance is missing from Settings.';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Watermark image scale check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Image watermark size spans 20%–60% of artwork width and permits enlargement.\n";

// End of file.
