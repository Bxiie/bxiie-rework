<?php

declare(strict_types=1);

/**
 * Prints recent tenant settings audit events.
 */

use App\Platform\Audit\AuditLogRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$repo = new AuditLogRepository(Database::connect($root));

$events = array_filter(
    $repo->latest(100),
    static fn (array $row): bool => (string) $row['action'] === 'tenant.settings.updated',
);

echo json_encode(array_values($events), JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
