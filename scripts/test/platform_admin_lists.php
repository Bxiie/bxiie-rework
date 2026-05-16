<?php

declare(strict_types=1);

/**
 * Manual verification script for platform admin list repositories.
 */

use App\Platform\Email\EmailOutboxRepository;
use App\Platform\Tenants\TenantAdminRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);

echo json_encode([
    'tenants' => (new TenantAdminRepository($pdo))->latest(5),
    'email_outbox' => (new EmailOutboxRepository($pdo))->latest(5),
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
