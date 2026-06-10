<?php

declare(strict_types=1);

/**
 * Manual verification script for audit log writes.
 */

use App\Platform\Audit\AuditLogRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$repository = new AuditLogRepository(Database::connect($root));

$id = $repository->record(
    action: 'manual.audit_test',
    details: [
        'source' => 'scripts/test/audit_log.php',
    ],
    ipAddress: '127.0.0.1',
);

echo json_encode([
    'audit_log_id' => $id,
    'latest' => $repository->latest(3),
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
