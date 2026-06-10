<?php
/**
 * Debugs the tenant opt-in contract used by the public ArtsFolio directory.
 *
 * Run from the project root:
 *   php scripts/debug/check_directory_contract.php
 */

declare(strict_types=1);

use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$sql = "
    SELECT
        t.id,
        t.slug,
        t.name,
        t.status,
        opt.setting_value AS directory_opt_in,
        summary.setting_value AS directory_summary,
        d.hostname AS primary_hostname,
        d.status AS primary_domain_status
    FROM tenants t
    LEFT JOIN tenant_settings opt
        ON opt.tenant_id = t.id
       AND opt.setting_key = 'platform_directory_opt_in'
    LEFT JOIN tenant_settings summary
        ON summary.tenant_id = t.id
       AND summary.setting_key = 'platform_directory_summary'
    LEFT JOIN tenant_domains d
        ON d.tenant_id = t.id
       AND d.is_primary = TRUE
    ORDER BY t.slug
";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

foreach ($rows as $row) {
    echo json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

// End of file.
