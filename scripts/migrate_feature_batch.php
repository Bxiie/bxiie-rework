<?php
/**
 * Idempotent production database migration for the Bxiie feature batch.
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$db = $container['db'];
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function columnExists(PDO $db, string $table, string $column): bool
{
    $stmt = $db->query('PRAGMA table_info(' . $table . ')');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (($row['name'] ?? '') === $column) {
            return true;
        }
    }
    return false;
}

function addColumn(PDO $db, string $table, string $column, string $definition): void
{
    if (columnExists($db, $table, $column)) {
        echo "Column exists: {$table}.{$column}\n";
        return;
    }
    $db->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    echo "Added column: {$table}.{$column}\n";
}

addColumn($db, 'exhibitions', 'state', 'TEXT');
addColumn($db, 'exhibitions', 'display_date', 'TEXT');
addColumn($db, 'exhibitions', 'event_type', 'TEXT');
addColumn($db, 'exhibitions', 'work_name', 'TEXT');
addColumn($db, 'exhibitions', 'additional_info', 'TEXT');
addColumn($db, 'page_views', 'city', 'TEXT');
addColumn($db, 'page_views', 'state', 'TEXT');
addColumn($db, 'page_views', 'country', 'TEXT');

$tenantRows = $db->query('SELECT id, display_name FROM tenants')->fetchAll(PDO::FETCH_ASSOC);
$defaults = [
    'browser_title' => null,
    'artist_name' => null,
    'copyright_name' => null,
    'home_intro' => '',
    'background_mode' => 'single',
    'background_tile_size' => '360px',
    'background_opacity' => '0.12',
];

foreach ($tenantRows as $tenant) {
    foreach ($defaults as $key => $default) {
        $value = $default ?? (string) $tenant['display_name'];
        $stmt = $db->prepare('INSERT OR IGNORE INTO settings (tenant_id, setting_key, setting_value) VALUES (:tenant_id, :key, :value)');
        $stmt->execute(['tenant_id' => $tenant['id'], 'key' => $key, 'value' => $value]);
    }
}

echo "Feature batch migration complete.\n";

// End of file.
