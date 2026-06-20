<?php

declare(strict_types=1);

/**
 * Regression check for tenant-filtered audit log search.
 *
 * The audit_log table enforces tenant and user foreign keys. This test resolves
 * the tenant by hostname and uses an existing user fixture instead of assuming
 * user ID 2 exists in every development or production database.
 */

use App\Platform\Audit\AuditLogRepository;
use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require_once __DIR__ . '/TestEnvironment.php';
TestEnvironment::skipIfProduction(basename(__FILE__));
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$tenant = (new TenantResolver($pdo))->resolveFromHost('bxiie.com');
$userId = (int) ($pdo->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn() ?: 0);

if (!$tenant) {
    fwrite(STDERR, "Missing expected tenant for this test.\n");
    exit(1);
}

if ($userId < 1) {
    fwrite(STDERR, "Tenant audit log list test requires at least one user fixture.\n");
    exit(1);
}

$repo = new AuditLogRepository($pdo);
$pdo->beginTransaction();

try {
    $id = $repo->record(
        action: 'manual.tenant_audit_list_test',
        tenantId: $tenant->tenantId,
        userId: $userId,
        entityType: 'test',
        entityId: 'tenant-audit-log-list',
        details: ['source' => 'scripts/test/tenant_audit_log_list.php'],
        ipAddress: '127.0.0.1',
    );

    $matches = $repo->search(
        action: 'manual.tenant_audit_list_test',
        tenantId: $tenant->tenantId,
        userId: $userId,
        limit: 5,
    );

    if (count($matches) < 1) {
        throw new RuntimeException('Tenant audit log search did not return the inserted test row.');
    }

    echo json_encode([
        'created_id' => $id,
        'tenant_id' => $tenant->tenantId,
        'user_id' => $userId,
        'match_count' => count($matches),
        'matches' => $matches,
    ], JSON_PRETTY_PRINT) . PHP_EOL;

    $pdo->rollBack();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

// End of file.
