<?php
/**
 * Apply schema/settings additions for Cloudflare Turnstile, tenant CSS, subscriber export,
 * editable slugs, page image sizes, and IP geolocation cache.
 */

declare(strict_types=1);

function logLine(string $message): void
{
    fwrite(STDOUT, "[feature-migrate] {$message}\n");
}

function setting(PDO $db, int $tenantId, string $key, string $value): void
{
    $stmt = $db->prepare(
        'INSERT INTO settings (tenant_id, setting_key, setting_value)
         VALUES (:tenant_id, :key, :value)
         ON CONFLICT(tenant_id, setting_key) DO NOTHING'
    );
    $stmt->execute(['tenant_id' => $tenantId, 'key' => $key, 'value' => $value]);
}

$databasePath = getenv('DATABASE_PATH') ?: __DIR__ . '/../database/bxiie.sqlite';
$db = new PDO('sqlite:' . $databasePath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys = ON');

$db->exec(
    'CREATE TABLE IF NOT EXISTS ip_geolocations (
        ip_hash TEXT PRIMARY KEY,
        country_code TEXT,
        city TEXT,
        state TEXT,
        country TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )'
);
$db->exec('CREATE INDEX IF NOT EXISTS idx_page_views_location ON page_views(tenant_id, country, state, city, created_at)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_page_views_image ON page_views(tenant_id, image_id, created_at)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_subscribers_tenant_email ON subscribers(tenant_id, email)');

$tenantRows = $db->query('SELECT id FROM tenants')->fetchAll(PDO::FETCH_ASSOC);
foreach ($tenantRows as $row) {
    $tenantId = (int) $row['id'];
    setting($db, $tenantId, 'portfolio_slug', 'portfolio');
    setting($db, $tenantId, 'about_slug', 'about');
    setting($db, $tenantId, 'contact_slug', 'contact');
    setting($db, $tenantId, 'about_image_size', 'medium');
    setting($db, $tenantId, 'contact_image_size', 'medium');
    setting($db, $tenantId, 'turnstile_site_key', '');
    setting($db, $tenantId, 'turnstile_secret_key', '');
    setting($db, $tenantId, 'tenant_css', '');
}

logLine('Migration complete.');

// End of file.
