<?php

// Verifies public watermark rendering, thumbnail exclusion, and cache hardening.

declare(strict_types=1);

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
    'public, max-age=86400, immutable',
    'storage/cache/watermarks/',
    'HTTP_IF_NONE_MATCH',
    'X-ArtsFolio-Watermark',
    'thumbnail-excluded',
];

$controllerForbiddenNeedles = [
    'public, max-age=0, must-revalidate',
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

foreach ($controllerForbiddenNeedles as $needle) {
    if (str_contains($controller, $needle)) {
        fwrite(
            STDERR,
            "[FAIL] Obsolete text remains in MediaController.php: {$needle}\n"
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

$etagCheckPosition = strpos($controller, 'HTTP_IF_NONE_MATCH');
$renderPosition = strpos($controller, '$watermark->render(');
if (
    $etagCheckPosition === false
    || $renderPosition === false
    || $etagCheckPosition > $renderPosition
) {
    fwrite(
        STDERR,
        "[FAIL] Conditional ETag handling must occur before watermark rendering.\n"
    );
    exit(1);
}

echo "[PASS] Watermark runtime, cache, and thumbnail exclusion checks passed.\n";

// End of file.
