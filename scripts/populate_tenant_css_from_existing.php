<?php
/**
 * Populate tenant-specific editable CSS from the existing site CSS.
 *
 * This script is intentionally conservative:
 * - It looks for the current CSS file in common project locations.
 * - It writes into tenant settings only when the editable CSS value is empty.
 * - Use --force to overwrite existing tenant CSS.
 *
 * Expected usage from production after Git deployment:
 *
 *   sudo -u www-data \
 *     DATABASE_PATH='/var/lib/bxiie-cms/database/bxiie.sqlite' \
 *     php scripts/populate_tenant_css_from_existing.php \
 *       --tenant=bxiie
 *
 * Force overwrite:
 *
 *   sudo -u www-data \
 *     DATABASE_PATH='/var/lib/bxiie-cms/database/bxiie.sqlite' \
 *     php scripts/populate_tenant_css_from_existing.php \
 *       --tenant=bxiie \
 *       --force
 */

declare(strict_types=1);

function usage(): void
{
    fwrite(STDERR, <<<TEXT
Usage:
  php scripts/populate_tenant_css_from_existing.php [options]

Options:
  --tenant=bxiie          Tenant slug. Default: bxiie.
  --css=/path/file.css    Explicit CSS file to import.
  --setting=custom_css    Setting key to update. Default: custom_css.
  --force                 Overwrite existing tenant CSS.
  --dry-run               Show what would happen without writing.
  --help                  Show this help.

TEXT);
}

function fail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function logLine(string $message): void
{
    fwrite(STDOUT, "[tenant-css] {$message}\n");
}

function hasFlag(array $options, string $name): bool
{
    return array_key_exists($name, $options);
}

function optionValue(array $options, string $name, ?string $default = null): ?string
{
    return isset($options[$name]) && is_string($options[$name]) ? $options[$name] : $default;
}

function loadPdo(string $databasePath): PDO
{
    if (!is_file($databasePath)) {
        fail("Database file not found: {$databasePath}");
    }

    $pdo = new PDO('sqlite:' . $databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    return $pdo;
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
    $stmt->execute(['name' => $table]);

    return (bool) $stmt->fetch();
}

function columnNames(PDO $pdo, string $table): array
{
    $rows = $pdo->query("PRAGMA table_info({$table})")->fetchAll();
    return array_map(static fn(array $row): string => (string) $row['name'], $rows);
}

function getTenantId(PDO $pdo, string $tenantSlug): int
{
    $stmt = $pdo->prepare('SELECT id FROM tenants WHERE slug = :slug');
    $stmt->execute(['slug' => $tenantSlug]);
    $row = $stmt->fetch();

    if (!$row) {
        fail("Tenant not found: {$tenantSlug}");
    }

    return (int) $row['id'];
}

function findCssFile(?string $explicitPath): string
{
    if ($explicitPath !== null) {
        if (!is_file($explicitPath)) {
            fail("Explicit CSS file not found: {$explicitPath}");
        }

        return $explicitPath;
    }

    $root = dirname(__DIR__);
    $candidates = [
        "{$root}/public/assets/css/site.css",
        "{$root}/public/assets/css/app.css",
        "{$root}/public/css/site.css",
        "{$root}/public/css/app.css",
        "{$root}/public/style.css",
        "{$root}/public/assets/style.css",
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    fail(
        "Could not find existing CSS automatically. Re-run with --css=/full/path/to/file.css"
    );
}

function detectSettingsTable(PDO $pdo): string
{
    foreach (['tenant_settings', 'site_settings', 'settings'] as $table) {
        if (tableExists($pdo, $table)) {
            return $table;
        }
    }

    fail('No supported settings table found. Expected tenant_settings, site_settings, or settings.');
}

function readSetting(PDO $pdo, string $table, int $tenantId, string $key): ?string
{
    $columns = columnNames($pdo, $table);

    $keyColumn = in_array('setting_key', $columns, true) ? 'setting_key' : 'key';
    $valueColumn = in_array('setting_value', $columns, true) ? 'setting_value' : 'value';

    if (!in_array($keyColumn, $columns, true) || !in_array($valueColumn, $columns, true)) {
        fail("Unsupported settings table shape for {$table}.");
    }

    if (in_array('tenant_id', $columns, true)) {
        $stmt = $pdo->prepare(
            "SELECT {$valueColumn} AS value FROM {$table} WHERE tenant_id = :tenant_id AND {$keyColumn} = :setting_key"
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'setting_key' => $key,
        ]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT {$valueColumn} AS value FROM {$table} WHERE {$keyColumn} = :setting_key"
        );
        $stmt->execute([
            'setting_key' => $key,
        ]);
    }

    $row = $stmt->fetch();

    return $row ? (string) $row['value'] : null;
}

function writeSetting(PDO $pdo, string $table, int $tenantId, string $key, string $value): void
{
    $columns = columnNames($pdo, $table);

    $keyColumn = in_array('setting_key', $columns, true) ? 'setting_key' : 'key';
    $valueColumn = in_array('setting_value', $columns, true) ? 'setting_value' : 'value';

    if (!in_array($keyColumn, $columns, true) || !in_array($valueColumn, $columns, true)) {
        fail("Unsupported settings table shape for {$table}.");
    }

    if (in_array('tenant_id', $columns, true)) {
        $existing = readSetting($pdo, $table, $tenantId, $key);

        if ($existing === null) {
            $stmt = $pdo->prepare(
                "INSERT INTO {$table} (tenant_id, {$keyColumn}, {$valueColumn}) VALUES (:tenant_id, :setting_key, :setting_value)"
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'setting_key' => $key,
                'setting_value' => $value,
            ]);
            return;
        }

        $stmt = $pdo->prepare(
            "UPDATE {$table} SET {$valueColumn} = :setting_value WHERE tenant_id = :tenant_id AND {$keyColumn} = :setting_key"
        );
        $stmt->execute([
            'setting_value' => $value,
            'tenant_id' => $tenantId,
            'setting_key' => $key,
        ]);
        return;
    }

    $existing = readSetting($pdo, $table, $tenantId, $key);

    if ($existing === null) {
        $stmt = $pdo->prepare(
            "INSERT INTO {$table} ({$keyColumn}, {$valueColumn}) VALUES (:setting_key, :setting_value)"
        );
        $stmt->execute([
            'setting_key' => $key,
            'setting_value' => $value,
        ]);
        return;
    }

    $stmt = $pdo->prepare(
        "UPDATE {$table} SET {$valueColumn} = :setting_value WHERE {$keyColumn} = :setting_key"
    );
    $stmt->execute([
        'setting_value' => $value,
        'setting_key' => $key,
    ]);
}

$options = getopt('', [
    'tenant::',
    'css::',
    'setting::',
    'force',
    'dry-run',
    'help',
]);

if ($options === false || hasFlag($options, 'help')) {
    usage();
    exit($options === false ? 1 : 0);
}

$databasePath = getenv('DATABASE_PATH') ?: dirname(__DIR__) . '/database/bxiie.sqlite';
$tenantSlug = optionValue($options, 'tenant', 'bxiie') ?? 'bxiie';
$settingKey = optionValue($options, 'setting', 'custom_css') ?? 'custom_css';
$explicitCssPath = optionValue($options, 'css');
$force = hasFlag($options, 'force');
$dryRun = hasFlag($options, 'dry-run');

$cssPath = findCssFile($explicitCssPath);
$css = file_get_contents($cssPath);

if ($css === false || trim($css) === '') {
    fail("CSS file is empty or unreadable: {$cssPath}");
}

$pdo = loadPdo($databasePath);
$tenantId = getTenantId($pdo, $tenantSlug);
$settingsTable = detectSettingsTable($pdo);
$currentCss = readSetting($pdo, $settingsTable, $tenantId, $settingKey);

logLine("Tenant: {$tenantSlug} #{$tenantId}");
logLine("Database: {$databasePath}");
logLine("Settings table: {$settingsTable}");
logLine("Setting key: {$settingKey}");
logLine("CSS source: {$cssPath}");
logLine("CSS bytes: " . strlen($css));

if ($currentCss !== null && trim($currentCss) !== '' && !$force) {
    logLine('Tenant CSS already has content. No changes made. Use --force to overwrite.');
    exit(0);
}

if ($dryRun) {
    logLine($currentCss === null ? 'Would create tenant CSS setting.' : 'Would update tenant CSS setting.');
    exit(0);
}

writeSetting($pdo, $settingsTable, $tenantId, $settingKey, $css);
logLine('Tenant CSS populated successfully.');

// End of file.
