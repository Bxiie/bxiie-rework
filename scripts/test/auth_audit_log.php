<?php

declare(strict_types=1);

/**
 * Prints recent local auth audit events.
 */

use App\Platform\Audit\AuditLogRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$repository = new AuditLogRepository(Database::connect($root));

$events = array_filter(
    $repository->latest(50),
    static fn (array $row): bool => str_starts_with((string) $row['action'], 'auth.')
);

echo json_encode(array_values($events), JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
