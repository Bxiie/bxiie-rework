<?php
/**
 * Add settings used by the exhibitions display cleanup update.
 */

declare(strict_types=1);

$databasePath = getenv('DATABASE_PATH') ?: __DIR__ . '/../database/bxiie.sqlite';
$pdo = new PDO('sqlite:' . $databasePath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON');

$tenantRows = $pdo->query('SELECT id FROM tenants')->fetchAll();
$defaults = [
    'exhibitions_heading' => 'Recent exhibitions',
    'exhibitions_display_mode' => 'text',
];

$stmt = $pdo->prepare(
    'INSERT INTO settings (tenant_id, setting_key, setting_value)
     VALUES (:tenant_id, :setting_key, :setting_value)
     ON CONFLICT(tenant_id, setting_key) DO NOTHING'
);

foreach ($tenantRows as $tenant) {
    foreach ($defaults as $key => $value) {
        $stmt->execute([
            'tenant_id' => (int) $tenant['id'],
            'setting_key' => $key,
            'setting_value' => $value,
        ]);
    }
}

echo "Exhibitions/message cleanup migration complete.
";

// End of file.
