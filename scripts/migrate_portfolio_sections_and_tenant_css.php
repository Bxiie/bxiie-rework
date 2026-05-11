<?php
/**
 * Apply portfolio-section admin helpers and populate readable tenant CSS.
 *
 * The schema for portfolio_sections and image_sections already exists in the
 * scaffold. This migration focuses on data hygiene:
 * - Ensure every tenant has a tenant_css row.
 * - Populate tenant_css from public/assets/site.css when empty or when --force
 *   is used.
 * - Remove stray "End of file." comments from tenant-entered CSS content.
 */

declare(strict_types=1);

function logLine(string $message): void
{
    fwrite(STDOUT, "[portfolio-css-migration] {$message}\n");
}

function fail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

$options = getopt('', ['force', 'dry-run', 'help']);
if ($options === false || array_key_exists('help', $options)) {
    fwrite(STDERR, "Usage: php scripts/migrate_portfolio_sections_and_tenant_css.php [--force] [--dry-run]\n");
    exit($options === false ? 1 : 0);
}

$force = array_key_exists('force', $options);
$dryRun = array_key_exists('dry-run', $options);
$databasePath = getenv('DATABASE_PATH') ?: dirname(__DIR__) . '/database/bxiie.sqlite';
$cssPath = dirname(__DIR__) . '/public/assets/site.css';

if (!is_file($databasePath)) {
    fail("Database file not found: {$databasePath}");
}

if (!is_file($cssPath)) {
    fail("Default CSS file not found: {$cssPath}");
}

$defaultCss = file_get_contents($cssPath);
if ($defaultCss === false || trim($defaultCss) === '') {
    fail("Default CSS file is empty or unreadable: {$cssPath}");
}

$defaultCss = preg_replace('/\s*\/\*\s*End of file\.\s*\*\/\s*$/i', '', $defaultCss) ?? $defaultCss;
$defaultCss = rtrim($defaultCss) . PHP_EOL;

$db = new PDO('sqlite:' . $databasePath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('PRAGMA foreign_keys = ON');

$tenants = $db->query('SELECT id, slug FROM tenants ORDER BY id')->fetchAll();
$select = $db->prepare('SELECT setting_value FROM settings WHERE tenant_id = :tenant_id AND setting_key = "tenant_css"');
$upsert = $db->prepare(
    'INSERT INTO settings (tenant_id, setting_key, setting_value)
     VALUES (:tenant_id, "tenant_css", :setting_value)
     ON CONFLICT(tenant_id, setting_key) DO UPDATE SET setting_value = excluded.setting_value'
);

foreach ($tenants as $tenant) {
    $tenantId = (int) $tenant['id'];
    $select->execute(['tenant_id' => $tenantId]);
    $row = $select->fetch();
    $currentCss = $row ? (string) $row['setting_value'] : '';
    $cleanedCss = preg_replace('/\s*\/\*\s*End of file\.\s*\*\/\s*$/i', '', $currentCss) ?? $currentCss;
    $cleanedCss = rtrim($cleanedCss);

    $shouldReplace = $force || trim($currentCss) === '';
    $nextCss = $shouldReplace ? $defaultCss : ($cleanedCss === '' ? '' : $cleanedCss . PHP_EOL);

    if ($dryRun) {
        logLine('Tenant ' . $tenant['slug'] . ' #' . $tenantId . ': ' . ($shouldReplace ? 'would populate readable default CSS' : 'would preserve existing CSS and remove trailing End of file comment if present'));
        continue;
    }

    if ($shouldReplace || $nextCss !== $currentCss) {
        $upsert->execute([
            'tenant_id' => $tenantId,
            'setting_value' => $nextCss,
        ]);
        logLine('Tenant ' . $tenant['slug'] . ' #' . $tenantId . ': tenant_css updated.');
    } else {
        logLine('Tenant ' . $tenant['slug'] . ' #' . $tenantId . ': no tenant_css change needed.');
    }
}

logLine('Complete.');

// End of file.
