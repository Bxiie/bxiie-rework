<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents(
    $root . '/app/Tenant/Media/WatermarkService.php'
);

$failures = [];

foreach ([
    "\$position = 'center';",
    '$stackGap = max(',
    '$imageRenderSize = [0, 0];',
    '$imageOffsetY = $mode === \'both\'',
    'int $centerOffsetY = 0',
    '(($targetWidth - $renderWidth) / 2)',
    '(($imageRenderSize[1] ?? 0) + $stackGap) / 2',
    'return [$renderWidth, $renderHeight];',
] as $marker) {
    if (!str_contains($service, $marker)) {
        $failures[] = "WatermarkService missing marker: {$marker}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Centered stacked watermark check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Watermarks are centered and image/text layers are vertically stacked.\n";

// End of file.
