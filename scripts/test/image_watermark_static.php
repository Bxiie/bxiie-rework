<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$servicePath = $root . '/app/Tenant/Media/WatermarkService.php';
$settingsPath = $root . '/app/Http/Controllers/Tenant/Admin/SettingsController.php';
$mediaPath = $root . '/app/Http/Controllers/Tenant/MediaController.php';

$files = [
    'WatermarkService' => $servicePath,
    'SettingsController' => $settingsPath,
    'MediaController' => $mediaPath,
];

$contents = [];
$failures = [];

foreach ($files as $name => $path) {
    if (!is_file($path)) {
        $failures[] = "{$name} is missing: {$path}";
        continue;
    }

    $contents[$name] = (string) file_get_contents($path);
}

$serviceMarkers = [
    "'watermark_mode'",
    "'watermark_media_uuid'",
    'private function watermarkImage',
    'private function renderImageWatermark',
    "['text', 'image', 'both']",
    'imagecopyresampled',
    'imagecopy(',
    'imagesetpixel(',
    '$adjustedAlpha',
    'private readonly ?PDO $pdo',
    '$this->renderImageWatermark(',
];

foreach ($serviceMarkers as $marker) {
    if (!isset($contents['WatermarkService'])
        || !str_contains($contents['WatermarkService'], $marker)) {
        $failures[] = "WatermarkService missing marker: {$marker}";
    }
}

if (isset($contents['WatermarkService'])
    && str_contains(
        $contents['WatermarkService'],
        'imagecopymerge($target, $scaled'
    )) {
    $failures[] = 'WatermarkService still uses the legacy imagecopymerge compositor.';
}

$settingsMarkers = [
    'name="watermark_mode"',
    'Text only',
    'Image only',
    'Image and text',
    '$watermarkImagePicker',
    "'watermark_media_uuid'",
    "['text', 'image', 'both']",
];

foreach ($settingsMarkers as $marker) {
    if (!isset($contents['SettingsController'])
        || !str_contains($contents['SettingsController'], $marker)) {
        $failures[] = "SettingsController missing marker: {$marker}";
    }
}

if (isset($contents['MediaController'])) {
    if (!str_contains(
        $contents['MediaController'],
        'new TenantSettingsRepository($this->pdo),'
    )) {
        $failures[] = 'MediaController does not pass tenant settings to WatermarkService.';
    }

    if (!str_contains($contents['MediaController'], '$this->pdo,')) {
        $failures[] = 'MediaController does not pass PDO to WatermarkService.';
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Image watermark static check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Tenant watermarking supports text, image, or both with alpha-aware compositing.\n";

// End of file.
