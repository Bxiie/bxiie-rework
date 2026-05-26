<?php

declare(strict_types=1);

use App\Support\Database;

$root = dirname(__DIR__, 2);
require_once $root . '/app/bootstrap.php';

$pdo = Database::connect($root);

$settingsTable = null;
foreach (['tenant_settings', 'settings'] as $candidate) {
    $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($candidate));
    if ($stmt && $stmt->fetchColumn()) {
        $settingsTable = $candidate;
        break;
    }
}

if ($settingsTable === null) {
    fwrite(STDERR, "No tenant settings table found.\n");
    exit(1);
}

$sql = <<<SQL
SELECT
    t.id,
    t.slug,
    t.name AS tenant_name,
    opt.setting_value AS opted_in,
    COALESCE(primary_domain.hostname, fallback_domain.hostname, CONCAT(t.slug, '.artsfol.io')) AS resolved_domain,
    selected_thumbnail.setting_value AS selected_artwork_id,
    thumbnail_artwork.title AS selected_artwork_title,
    thumbnail_media.uuid AS selected_media_uuid
FROM tenants t
LEFT JOIN {$settingsTable} opt
    ON opt.tenant_id = t.id
   AND opt.setting_key = 'platform_directory_opt_in'
LEFT JOIN {$settingsTable} selected_thumbnail
    ON selected_thumbnail.tenant_id = t.id
   AND selected_thumbnail.setting_key = 'platform_directory_thumbnail_artwork_id'
LEFT JOIN artworks thumbnail_artwork
    ON thumbnail_artwork.tenant_id = t.id
   AND thumbnail_artwork.id = CAST(NULLIF(selected_thumbnail.setting_value, '') AS UNSIGNED)
   AND thumbnail_artwork.status = 'published'
LEFT JOIN media_assets thumbnail_media
    ON thumbnail_media.id = thumbnail_artwork.primary_media_id
   AND thumbnail_media.is_private = 0
LEFT JOIN tenant_domains primary_domain
    ON primary_domain.tenant_id = t.id
   AND primary_domain.is_primary = TRUE
   AND primary_domain.status = 'active'
LEFT JOIN tenant_domains fallback_domain
    ON fallback_domain.id = (
        SELECT td.id
        FROM tenant_domains td
        WHERE td.tenant_id = t.id
          AND td.status = 'active'
        ORDER BY td.is_primary DESC, td.id ASC
        LIMIT 1
    )
WHERE t.status = 'active'
ORDER BY t.name ASC
SQL;

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

echo json_encode([
    'ok' => true,
    'settings_table' => $settingsTable,
    'active_tenant_count' => count($rows),
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

// End of file.
