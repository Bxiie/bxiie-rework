<?php

declare(strict_types=1);

/**
 * Prints recent tenant admin action audit events.
 */

use App\Platform\Audit\AuditLogRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$repo = new AuditLogRepository(Database::connect($root));

$events = array_filter(
    $repo->latest(100),
    static fn (array $row): bool => in_array((string) $row['action'], [
        'tenant.contact_message.status_changed',
        'tenant.email_signup.consent_changed',
    ], true),
);

echo json_encode(array_values($events), JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
