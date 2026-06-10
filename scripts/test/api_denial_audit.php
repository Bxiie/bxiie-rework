<?php

declare(strict_types=1);

/**
 * Manual verification helper for API denial audit log records.
 */

use App\Platform\Audit\AuditLogRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$repository = new AuditLogRepository(Database::connect($root));

$latest = array_filter(
    $repository->latest(20),
    static fn (array $row): bool => str_starts_with((string) $row['action'], 'api.tenant_me.denied.')
);

echo json_encode(array_values($latest), JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
