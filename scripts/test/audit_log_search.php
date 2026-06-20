<?php

declare(strict_types=1);

/**
 * Regression check for audit log search filters.
 *
 * The audit_log table has foreign keys to tenants and users. This test resolves
 * real fixture rows instead of assuming specific auto-increment IDs, then wraps
 * the insert in a rollback-only transaction so preflight does not leave manual
 * audit rows behind.
 */

use App\Platform\Audit\AuditLogRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$tenantId = (int) ($pdo->query('SELECT id FROM tenants ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
$userId = (int) ($pdo->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn() ?: 0);

if ($tenantId < 1 || $userId < 1) {
    fwrite(STDERR, "Audit log search test requires at least one tenant and one user fixture.\n");
    exit(1);
}

$repo = new AuditLogRepository($pdo);
$pdo->beginTransaction();

try {
    $id = $repo->record(
        action: 'manual.audit_search_test',
        tenantId: $tenantId,
        userId: $userId,
        entityType: 'test',
        entityId: 'audit-search',
        details: ['source' => 'scripts/test/audit_log_search.php'],
        ipAddress: '127.0.0.1',
    );

    $matches = $repo->search(
        action: 'manual.audit_search_test',
        tenantId: $tenantId,
        userId: $userId,
        limit: 5,
    );

    if (count($matches) < 1) {
        throw new RuntimeException('Audit log search did not return the inserted test row.');
    }

    echo json_encode([
        'created_id' => $id,
        'tenant_id' => $tenantId,
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
