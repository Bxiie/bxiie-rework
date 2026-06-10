<?php

declare(strict_types=1);

/**
 * Manual verification script for audit log search filters.
 */

use App\Platform\Audit\AuditLogRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$repo = new AuditLogRepository(Database::connect($root));

$id = $repo->record(
    action: 'manual.audit_search_test',
    tenantId: 1,
    userId: 2,
    entityType: 'test',
    entityId: 'audit-search',
    details: ['source' => 'scripts/test/audit_log_search.php'],
    ipAddress: '127.0.0.1',
);

$matches = $repo->search(
    action: 'manual.audit_search_test',
    tenantId: 1,
    userId: 2,
    limit: 5,
);

echo json_encode([
    'created_id' => $id,
    'match_count' => count($matches),
    'matches' => $matches,
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
