<?php

declare(strict_types=1);

/**
 * Functional check for the home-hero watermark exemption's SQL (join/CAST
 * correctness) that the dependency-free
 * scripts/test/presentation_media_watermark_exclusion_static.php can't
 * catch with text matching alone. Not part of scripts/test/preflight.sh:
 * production's PHP build has no ext-pdo_sqlite, so this is a local/dev
 * check only — run it manually after touching MediaController's
 * presentation-media exemption logic.
 */

use App\Http\Controllers\Tenant\MediaController;
use App\Platform\Tenancy\TenantContext;

$root = dirname(__DIR__, 2);
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

echo "[PASS] Home hero watermark exclusion functional checks passed.\n";

// End of file.
