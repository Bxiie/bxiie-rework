<?php
/**
 * Add initial stats_started_at setting for each tenant.
 */

declare(strict_types=1);

$databasePath = getenv('DATABASE_PATH') ?: __DIR__ . '/../database/bxiie.sqlite';
$pdo = new PDO('sqlite:' . $databasePath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON');

$tenants = $pdo->query('SELECT id FROM tenants ORDER BY id')->fetchAll();

foreach ($tenants as $tenant) {
    $tenantId = (int) $tenant['id'];
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE tenant_id = :tenant_id AND setting_key = "stats_started_at"');
    $stmt->execute(['tenant_id' => $tenantId]);
    if ($stmt->fetch()) {
        continue;
    }

    $stmt = $pdo->prepare('SELECT MIN(created_at) AS started_at FROM page_views WHERE tenant_id = :tenant_id');
    $stmt->execute(['tenant_id' => $tenantId]);
    $row = $stmt->fetch();
    $startedAt = trim((string) ($row['started_at'] ?? ''));
    if ($startedAt === '') {
        $startedAt = date('Y-m-d H:i:s');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO settings (tenant_id, setting_key, setting_value)
         VALUES (:tenant_id, "stats_started_at", :started_at)'
    );
    $stmt->execute(['tenant_id' => $tenantId, 'started_at' => $startedAt]);
    echo "Set stats_started_at for tenant {$tenantId} to {$startedAt}\n";
}

echo "Stats reset migration complete.\n";

// End of file.
