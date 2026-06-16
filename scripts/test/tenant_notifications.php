<?php

declare(strict_types=1);

/**
 * Manual verification script for tenant contact/signup admin notifications.
 */

use App\Platform\Email\EmailOutboxRepository;
use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;
use App\Tenant\Contact\ContactNotificationService;
use App\Tenant\Settings\TenantSettingsRepository;
use App\Tenant\Signup\SignupNotificationService;

$root = dirname(__DIR__, 2);
require_once __DIR__ . '/TestEnvironment.php';
TestEnvironment::skipIfProduction(basename(__FILE__));
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$resolver = new TenantResolver($pdo);
$tenant = $resolver->resolveFromHost('bxiie.com');

if (!$tenant) {
    fwrite(STDERR, "No tenant resolved for bxiie.com\n");
    exit(1);
}

$settings = new TenantSettingsRepository($pdo);
$settings->set($tenant, 'site_admin_email', 'info@artsfol.io');

$outbox = new EmailOutboxRepository($pdo);

$contactId = (new ContactNotificationService($outbox, $settings))->queueContactNotification(
    tenant: $tenant,
    senderName: 'Contact Test',
    senderEmail: 'contact@example.test',
    message: 'This is a test contact message.',
);

$signupId = (new SignupNotificationService($outbox, $settings))->queueSignupNotification(
    tenant: $tenant,
    signupEmail: 'signup@example.test',
    signupName: 'Signup Test',
);

echo json_encode([
    'contact_email_id' => $contactId,
    'signup_email_id' => $signupId,
    'latest' => $outbox->latest(5),
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
