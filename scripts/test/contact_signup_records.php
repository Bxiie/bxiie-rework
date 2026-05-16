<?php

declare(strict_types=1);

/**
 * Manual verification script for persisted tenant contact messages and email signups.
 */

use App\Platform\Email\EmailOutboxRepository;
use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;
use App\Tenant\Contact\ContactMessageRepository;
use App\Tenant\Contact\ContactMessageService;
use App\Tenant\Contact\ContactNotificationService;
use App\Tenant\Settings\TenantSettingsRepository;
use App\Tenant\Signup\EmailSignupRepository;
use App\Tenant\Signup\EmailSignupService;
use App\Tenant\Signup\SignupNotificationService;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$resolver = new TenantResolver($pdo);
$tenant = $resolver->resolveFromHost('bxiie.com');

if (!$tenant) {
    fwrite(STDERR, "No tenant resolved for bxiie.com\n");
    exit(1);
}

$settings = new TenantSettingsRepository($pdo);
$settings->set($tenant, 'site_admin_email', 'admin@example.test');

$outbox = new EmailOutboxRepository($pdo);

$contactRepository = new ContactMessageRepository($pdo);
$signupRepository = new EmailSignupRepository($pdo);

$contactService = new ContactMessageService(
    messages: $contactRepository,
    notifications: new ContactNotificationService($outbox, $settings),
);

$signupService = new EmailSignupService(
    signups: $signupRepository,
    notifications: new SignupNotificationService($outbox, $settings),
);

$contactId = $contactService->receive(
    tenant: $tenant,
    senderName: 'Persistent Contact Test',
    senderEmail: 'persistent-contact@example.test',
    message: 'Persist this contact message.',
    subject: 'Persistence Test',
    ipAddress: '127.0.0.1',
    userAgent: 'manual-test',
);

$signupId = $signupService->receive(
    tenant: $tenant,
    email: 'persistent-signup@example.test',
    name: 'Persistent Signup Test',
    source: 'manual-test',
    ipAddress: '127.0.0.1',
    userAgent: 'manual-test',
);

echo json_encode([
    'contact_message_id' => $contactId,
    'email_signup_id' => $signupId,
    'latest_contact_messages' => $contactRepository->latestForTenant($tenant, 3),
    'latest_email_signups' => $signupRepository->latestForTenant($tenant, 3),
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
