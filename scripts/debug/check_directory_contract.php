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

function table_exists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
        return $stmt !== false && $stmt->fetchColumn() !== false;
    } catch (Throwable) {
        return false;
    }
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ' . $pdo->quote($column));
        return $stmt !== false && $stmt->fetchColumn() !== false;
    } catch (Throwable) {
        return false;
    }
}

$tenantNameColumn = column_exists($pdo, 'tenants', 'name') ? 'name' : 'display_name';
$settingsTable = table_exists($pdo, 'tenant_settings') ? 'tenant_settings' : 'settings';
$domainColumn = column_exists($pdo, 'tenant_domains', 'hostname') ? 'hostname' : 'domain';
$hasPrimary = column_exists($pdo, 'tenant_domains', 'is_primary');
$hasDomainStatus = column_exists($pdo, 'tenant_domains', 'status');

$directoryEnabled = 'default-on';
if (table_exists($pdo, 'platform_settings')) {
    $stmt = $pdo->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = 'platform_directory_enabled' LIMIT 1");
    $stmt->execute();
    $value = $stmt->fetchColumn();
    $directoryEnabled = $value === false ? 'default-on' : (string) $value;
}

echo "platform_directory_enabled={$directoryEnabled}" . PHP_EOL;
echo "tenants_name_column={$tenantNameColumn}" . PHP_EOL;
echo "settings_table={$settingsTable}" . PHP_EOL;
echo "tenant_domains_column={$domainColumn}" . PHP_EOL;
echo "domain_filters=" . json_encode(['is_primary' => $hasPrimary, 'status' => $hasDomainStatus], JSON_UNESCAPED_SLASHES) . PHP_EOL;

$domainJoinFilters = [];
if ($hasPrimary) {
    $domainJoinFilters[] = 'd.is_primary = 1';
}
if ($hasDomainStatus) {
    $domainJoinFilters[] = "d.status IN ('active', 'dns_verified', 'vhost_pending', 'cert_pending')";
}
$domainJoinSql = $domainJoinFilters === [] ? '' : ' AND ' . implode(' AND ', $domainJoinFilters);

$sql = "
    SELECT
        t.id,
        t.slug,
        t.{$tenantNameColumn} AS display_name,
        t.status,
        opt.setting_value AS directory_opt_in,
        summary.setting_value AS directory_summary,
        d.{$domainColumn} AS directory_hostname
    FROM tenants t
    LEFT JOIN {$settingsTable} opt
        ON opt.tenant_id = t.id
       AND opt.setting_key = 'platform_directory_opt_in'
    LEFT JOIN {$settingsTable} summary
        ON summary.tenant_id = t.id
       AND summary.setting_key = 'platform_directory_summary'
    LEFT JOIN tenant_domains d
        ON d.tenant_id = t.id{$domainJoinSql}
    ORDER BY t.slug
";

foreach (($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
    $truthy = in_array(strtolower(trim((string) ($row['directory_opt_in'] ?? ''))), ['1', 'true', 'yes', 'on'], true);
    $eligible = $truthy && in_array((string) $row['status'], ['active', 'trial'], true);
    $row['eligible_for_directory'] = $eligible ? 'yes' : 'no';
    echo json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

// End of file.
