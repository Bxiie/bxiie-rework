<?php
/**
 * Debugs the tenant opt-in and thumbnail contract used by the public directory.
 *
 * Run from the project root:
 *   php scripts/debug/check_directory_thumbnail_contract.php
 */

declare(strict_types=1);

use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);

$sql = "
    SELECT
        t.id AS tenant_id,
        t.slug,
        t.name,
        t.status AS tenant_status,
        opt.setting_value AS opted_in,
        thumb.setting_value AS thumbnail_artwork_id,
        a.title AS thumbnail_artwork_title,
        a.status AS artwork_status,
        m.uuid AS media_uuid,
        m.storage_path,
        d.hostname AS primary_hostname,
        d.status AS primary_domain_status
    FROM tenants t
    LEFT JOIN tenant_settings opt
        ON opt.tenant_id = t.id
       AND opt.setting_key = 'platform_directory_opt_in'
    LEFT JOIN tenant_settings thumb
        ON thumb.tenant_id = t.id
       AND thumb.setting_key = 'platform_directory_thumbnail_artwork_id'
    LEFT JOIN artworks a
        ON a.tenant_id = t.id
       AND a.id = CAST(NULLIF(thumb.setting_value, '') AS UNSIGNED)
    LEFT JOIN media_assets m
        ON m.id = a.primary_media_id
    LEFT JOIN tenant_domains d
        ON d.tenant_id = t.id
       AND d.is_primary = TRUE
    WHERE LOWER(TRIM(COALESCE(opt.setting_value, ''))) IN ('1', 'true', 'yes', 'on')
    ORDER BY t.name ASC
";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

$result = [
    'ok' => true,
    'opted_in_count' => count($rows),
    'problems' => [],
    'tenants' => $rows,
];

foreach ($rows as $row) {
    $slug = (string) ($row['slug'] ?? 'unknown-tenant');

    if ((string) ($row['thumbnail_artwork_id'] ?? '') === '') {
        $result['ok'] = false;
        $result['problems'][] = $slug . ' is opted in but has no platform_directory_thumbnail_artwork_id setting.';
        continue;
    }

    if ((string) ($row['artwork_status'] ?? '') !== 'published') {
        $result['ok'] = false;
        $result['problems'][] = $slug . ' selected thumbnail artwork is not published or no longer exists.';
    }

    if ((string) ($row['media_uuid'] ?? '') === '') {
        $result['ok'] = false;
        $result['problems'][] = $slug . ' selected thumbnail artwork has no primary media image.';
    }

    if ((string) ($row['primary_hostname'] ?? '') === '') {
        $result['ok'] = false;
        $result['problems'][] = $slug . ' has no primary tenant domain, so directory links cannot be built.';
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

// End of file.
