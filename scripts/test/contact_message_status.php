<?php

declare(strict_types=1);

/**
 * Manual verification script for tenant contact message status updates.
 */

use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;
use App\Tenant\Contact\ContactMessageRepository;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$tenant = (new TenantResolver($pdo))->resolveFromHost('bxiie.com');

if (!$tenant) {
    fwrite(STDERR, "Missing bxiie tenant.
");
    exit(1);
}

$repo = new ContactMessageRepository($pdo);
$messageId = $repo->create(
    tenant: $tenant,
    senderName: 'Status Test',
    senderEmail: 'status@example.test',
    message: 'Status workflow test.',
    subject: 'Status Test',
    ipAddress: '127.0.0.1',
    userAgent: 'manual-test',
);

$repo->updateStatus($tenant, $messageId, 'read');
$repo->updateStatus($tenant, $messageId, 'archived');

echo json_encode([
    'message_id' => $messageId,
    'latest' => $repo->latestForTenant($tenant, 5),
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
