<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents($root . '/app/Tenant/Media/WatermarkService.php');
$settings = (string) file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/SettingsController.php');
$media = (string) file_get_contents($root . '/app/Http/Controllers/Tenant/MediaController.php');
$failures = [];
foreach (["'watermark_mode'", "'watermark_media_uuid'", 'private function watermarkImage', 'private function renderImageWatermark', "['text', 'image', 'both']", 'imagecopyresampled', 'imagecopymerge', 'private readonly ?PDO $pdo'] as $marker) {
    if (!str_contains($service, $marker)) { $failures[] = "WatermarkService missing marker: {$marker}"; }
}
foreach (['name="watermark_mode"', 'Text only', 'Image only', 'Image and text', '$watermarkImagePicker', "'watermark_media_uuid'", "['text', 'image', 'both']"] as $marker) {
    if (!str_contains($settings, $marker)) { $failures[] = "SettingsController missing marker: {$marker}"; }
}
if (!str_contains($media, 'new TenantSettingsRepository($this->pdo),') || !str_contains($media, '$this->pdo,')) {
    $failures[] = 'MediaController does not pass PDO to WatermarkService.';
}
if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Image watermark static check failed:\n");
    foreach ($failures as $failure) { fwrite(STDERR, "[FAIL]  - {$failure}\n"); }
    exit(1);
}
echo "[PASS] Tenant watermarking supports text, image, or both.\n";
