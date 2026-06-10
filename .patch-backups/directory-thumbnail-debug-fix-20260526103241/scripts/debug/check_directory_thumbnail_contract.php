<?php

declare(strict_types=1);

require __DIR__ . '/../../bootstrap/app.php';

use App\Core\Database\ConnectionFactory;

$pdo = ConnectionFactory::fromConfig(require __DIR__ . '/../../config/database.php');

$sql = "
    SELECT
        t.id AS tenant_id,
        t.slug,
        t.name,
        opt.setting_value AS opted_in,
        thumb.setting_value AS thumbnail_artwork_id,
        a.title AS thumbnail_artwork_title,
        a.status AS artwork_status,
        m.uuid AS media_uuid,
        m.storage_path
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
    if ((string) ($row['thumbnail_artwork_id'] ?? '') === '') {
        $result['ok'] = false;
        $result['problems'][] = $row['slug'] . ' is opted in but has no platform_directory_thumbnail_artwork_id setting.';
        continue;
    }

    if ((string) ($row['artwork_status'] ?? '') !== 'published') {
        $result['ok'] = false;
        $result['problems'][] = $row['slug'] . ' selected thumbnail artwork is not published or no longer exists.';
    }

    if ((string) ($row['media_uuid'] ?? '') === '') {
        $result['ok'] = false;
        $result['problems'][] = $row['slug'] . ' selected thumbnail artwork has no primary media image.';
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

// End of file.
