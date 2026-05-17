<?php

declare(strict_types=1);

/**
 * Manual verification script for tenant-filtered audit log search.
 */

use App\Platform\Audit\AuditLogRepository;
use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$tenant = (new TenantResolver($pdo))->resolveFromHost('bxiie.com');

if (!$tenant) {
    fwrite(STDERR, "Missing bxiie tenant.\n");
    exit(1);
}

$repo = new AuditLogRepository($pdo);
$id = $repo->record(
    action: 'manual.tenant_audit_list_test',
    tenantId: $tenant->tenantId,
    userId: 2,
    entityType: 'test',
    entityId: 'tenant-audit-log-list',
    details: ['source' => 'scripts/test/tenant_audit_log_list.php'],
    ipAddress: '127.0.0.1',
);

$matches = $repo->search(
    action: 'manual.tenant_audit_list_test',
    tenantId: $tenant->tenantId,
    userId: 2,
    limit: 5,
);

echo json_encode([
    'created_id' => $id,
    'tenant_id' => $tenant->tenantId,
    'match_count' => count($matches),
    'matches' => $matches,
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
