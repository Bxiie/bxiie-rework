<?php

declare(strict_types=1);

/**
 * Manual verification script for tenant admin contact/signup list repositories.
 */

use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;
use App\Tenant\Contact\ContactMessageRepository;
use App\Tenant\Signup\EmailSignupRepository;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$tenant = (new TenantResolver($pdo))->resolveFromHost('bxiie.com');

if (!$tenant) {
    fwrite(STDERR, "Missing bxiie tenant.\n");
    exit(1);
}

echo json_encode([
    'contact_messages' => (new ContactMessageRepository($pdo))->latestForTenant($tenant, 5),
    'email_signups' => (new EmailSignupRepository($pdo))->latestForTenant($tenant, 5),
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
