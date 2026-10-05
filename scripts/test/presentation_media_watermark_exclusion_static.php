<?php

declare(strict_types=1);

/**
 * Regression checks for presentation-media watermark exclusion.
 *
 * Kept dependency-free (no PDO/sqlite) because this file is invoked directly
 * by scripts/test/preflight.sh on every production deploy, whose PHP build
 * does not include ext-pdo_sqlite. A functional check of the underlying SQL
 * (join/CAST correctness) lives separately in
 * scripts/test/home_hero_watermark_exclusion.php, which is not part of the
 * deploy gate.
 */

$root = dirname(__DIR__, 2);
$path = $root . '/app/Http/Controllers/Tenant/MediaController.php';
$source = file_get_contents($path);

if ($source === false) {
    fwrite(STDERR, "[FAIL] Could not read MediaController.php.\n");
    exit(1);
}

$needles = [
    '&& !$this->isSelectedPresentationMedia(',
    'private function isSelectedPresentationMedia(',
    "'background_media_uuid'",
    "'menu_media_uuid'",
    "'topbar_media_uuid'",
    "'artwork_card_media_uuid'",
    'AND setting_value = :media_uuid',
    "\$variantKey !== 'thumb'",
    'private function isHomeHeroMedia(TenantContext $tenant, string $mediaUuid): bool',
    "hero.setting_key = 'home_hero_artwork_id'",
];

foreach ($needles as $needle) {
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, "[FAIL] MediaController.php missing: {$needle}\n");
        exit(1);
    }
}

echo "[PASS] Presentation-media watermark exclusion checks passed.\n";

// End of file.
