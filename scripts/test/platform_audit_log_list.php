<?php

declare(strict_types=1);

/**
 * Manual verification script for platform audit log list repository access.
 */

use App\Platform\Audit\AuditLogRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$repo = new AuditLogRepository(Database::connect($root));

if (count($repo->latest(1)) === 0) {
    $repo->record(
        action: 'manual.platform_audit_log_list_test',
        entityType: 'test',
        entityId: 'platform_audit_log_list',
        details: ['source' => 'scripts/test/platform_audit_log_list.php'],
        ipAddress: '127.0.0.1',
    );
}

echo json_encode([
    'latest' => $repo->latest(5),
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
