<?php

declare(strict_types=1);

/**
 * Manual verification script for queuing account and lifecycle emails.
 */

use App\Platform\Email\EmailOutboxRepository;
use App\Platform\Email\LifecycleEmailService;
use App\Platform\Email\TemplateRenderer;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$outbox = new EmailOutboxRepository($pdo);
$service = new LifecycleEmailService(
    outbox: $outbox,
    renderer: new TemplateRenderer(),
    templateRoot: $root . '/template',
);

$passwordResetId = $service->queuePasswordReset(
    recipientEmail: 'password-reset-test@example.test',
    resetUrl: 'https://artsfol.io/reset-password/example-token',
);

$emailVerificationId = $service->queueEmailVerification(
    recipientEmail: 'email-verification-test@example.test',
    verificationUrl: 'https://artsfol.io/verify-email/example-token',
);

$welcomeId = $service->queueWelcome(
    recipientEmail: 'welcome-test@example.test',
    recipientName: 'Test User',
);

echo json_encode([
    'password_reset_email_id' => $passwordResetId,
    'email_verification_email_id' => $emailVerificationId,
    'welcome_email_id' => $welcomeId,
    'latest' => $outbox->latest(5),
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
