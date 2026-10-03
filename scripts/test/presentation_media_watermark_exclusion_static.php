<?php

declare(strict_types=1);

/** Regression checks for presentation-media watermark exclusion. */

use App\Http\Controllers\Tenant\MediaController;
use App\Platform\Tenancy\TenantContext;

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

// Functional check: the home-page hero's image must be exempt from
// watermarking, tracking whichever artwork is currently the hero (and its
// current primary image) rather than a fixed stored UUID — static text
// checks alone wouldn't catch a wrong join or a bad CAST.
require $root . '/vendor/autoload.php';

final class HeroWatermarkTestPdo extends Pdo\Sqlite
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $query = str_replace('CAST(hero.setting_value AS UNSIGNED)', 'CAST(hero.setting_value AS INTEGER)', $query);

        return parent::prepare($query, $options);
    }
}

$pdo = new HeroWatermarkTestPdo('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec(
    "CREATE TABLE tenant_settings (tenant_id INTEGER, setting_key TEXT, setting_value TEXT);
     CREATE TABLE artworks (id INTEGER, tenant_id INTEGER, primary_media_id INTEGER);
     CREATE TABLE media_assets (id INTEGER, tenant_id INTEGER, uuid TEXT);
     INSERT INTO artworks VALUES (1, 1, 10), (2, 1, 20);
     INSERT INTO media_assets VALUES (10, 1, 'hero-media-uuid'), (20, 1, 'other-media-uuid');
     INSERT INTO tenant_settings VALUES (1, 'home_hero_artwork_id', '1');"
);

$tenant = new TenantContext(1, 'uuid', 'test', 'Test', 'test.local', 'custom', true);
$controller = new MediaController($pdo);
$isSelectedPresentationMedia = new ReflectionMethod($controller, 'isSelectedPresentationMedia');

if ($isSelectedPresentationMedia->invoke($controller, $tenant, 'hero-media-uuid') !== true) {
    fwrite(STDERR, "[FAIL] The current hero artwork's media must be exempt from watermarking.\n");
    exit(1);
}
if ($isSelectedPresentationMedia->invoke($controller, $tenant, 'other-media-uuid') !== false) {
    fwrite(STDERR, "[FAIL] A non-hero artwork's media must not be exempt from watermarking.\n");
    exit(1);
}

// Changing the hero must change which media is exempt — no stale UUID left behind.
$pdo->exec("UPDATE tenant_settings SET setting_value = '2' WHERE setting_key = 'home_hero_artwork_id'");
if ($isSelectedPresentationMedia->invoke($controller, $tenant, 'hero-media-uuid') !== false) {
    fwrite(STDERR, "[FAIL] The previous hero's media must lose its exemption once a different artwork becomes the hero.\n");
    exit(1);
}
if ($isSelectedPresentationMedia->invoke($controller, $tenant, 'other-media-uuid') !== true) {
    fwrite(STDERR, "[FAIL] The new hero's media must become exempt.\n");
    exit(1);
}

// No hero configured at all: nothing should be exempt via this path.
$pdo->exec("UPDATE tenant_settings SET setting_value = '' WHERE setting_key = 'home_hero_artwork_id'");
if ($isSelectedPresentationMedia->invoke($controller, $tenant, 'hero-media-uuid') !== false) {
    fwrite(STDERR, "[FAIL] With no hero configured, no media should be exempt via the hero path.\n");
    exit(1);
}

echo "[PASS] Presentation-media watermark exclusion checks passed.\n";

// End of file.
