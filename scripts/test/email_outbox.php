<?php

declare(strict_types=1);

/**
 * Manual verification script for account and lifecycle email queueing.
 *
 * This script runs inside a rollback-only transaction by default. It verifies
 * outbox rendering and lifecycle guards without leaving deliverable rows for
 * the production worker. Set ARTSFOLIO_EMAIL_OUTBOX_TEST_COMMIT=1 only with a
 * safe local mailbox or SMTP sink.
 */

use App\Platform\Email\EmailOutboxRepository;
use App\Platform\Email\LifecycleEmailService;
use App\Platform\Email\TemplateRenderer;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$commit = ((string) getenv('ARTSFOLIO_EMAIL_OUTBOX_TEST_COMMIT')) === '1';
$recipientEmail = 'welcome-test@example.test';

$pdo = Database::connect($root);
$pdo->beginTransaction();

try {
    $outbox = new EmailOutboxRepository($pdo);
    $service = new LifecycleEmailService(
        outbox: $outbox,
        renderer: new TemplateRenderer(),
        templateRoot: $root . '/template',
    );

    $passwordResetId = $service->queuePasswordReset(
        recipientEmail: $recipientEmail,
        resetUrl: 'https://artsfol.io/reset-password/example-token',
        userId: 1,
    );

    $emailVerificationId = $service->queueEmailVerification(
        recipientEmail: $recipientEmail,
        verificationUrl: 'https://artsfol.io/verify-email/example-token',
        userId: 1,
    );

    $guardTriggered = false;

    try {
        $service->queueWelcome(
            recipientEmail: $recipientEmail,
            recipientName: 'Test User',
        );
    } catch (InvalidArgumentException $e) {
        $guardTriggered = str_contains($e->getMessage(), 'without tenant_id and user_id');
    }

    if (!$guardTriggered) {
        throw new RuntimeException('Lifecycle welcome guard did not reject missing tenant_id/user_id.');
    }

    $welcomeId = $service->queueWelcome(
        recipientEmail: $recipientEmail,
        recipientName: 'Test User',
        tenantId: 1,
        userId: 1,
    );

    $latest = $outbox->latest(5);

    if ($commit) {
        $pdo->commit();
    } else {
        $pdo->rollBack();
    }

    echo json_encode([
        'ok' => true,
        'committed' => $commit,
        'recipient_email' => $recipientEmail,
        'password_reset_email_id' => $passwordResetId,
        'email_verification_email_id' => $emailVerificationId,
        'welcome_email_id' => $welcomeId,
        'lifecycle_guard_triggered' => $guardTriggered,
        'latest' => $latest,
    ], JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

// End of file.
