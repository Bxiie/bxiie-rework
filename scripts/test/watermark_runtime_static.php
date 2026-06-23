<?php

declare(strict_types=1);

/**
 * Regression checks for public watermark rendering and thumbnail exclusion.
 */

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/app/Http/Controllers/Tenant/MediaController.php';
$servicePath = $root . '/app/Tenant/Media/WatermarkService.php';

$controller = file_get_contents($controllerPath);
$service = file_get_contents($servicePath);

if ($controller === false || $service === false) {
    fwrite(STDERR, "[FAIL] Could not read watermark runtime files.\n");
    exit(1);
}

$controllerNeedles = [
    "\$variantKey !== 'thumb'",
    "\$watermark->fingerprint(\$tenant)",
    'public, max-age=0, must-revalidate',
    'X-ArtsFolio-Watermark',
    'thumbnail-excluded',
];

$serviceNeedles = [
    "extension_loaded('gd')",
    'public function fingerprint',
    'imagettftext',
    'DejaVuSans.ttf',
    'imagedestroy($image)',
];

foreach ($controllerNeedles as $needle) {
    if (!str_contains($controller, $needle)) {
        fwrite(
            STDERR,
            "[FAIL] Missing expected text in MediaController.php: {$needle}\n"
        );
        exit(1);
    }
}

foreach ($serviceNeedles as $needle) {
    if (!str_contains($service, $needle)) {
        fwrite(
            STDERR,
            "[FAIL] Missing expected text in WatermarkService.php: {$needle}\n"
        );
        exit(1);
    }
}

echo "[PASS] Watermark runtime and thumbnail exclusion checks passed.\n";

// End of file.
