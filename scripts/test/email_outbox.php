<?php

declare(strict_types=1);

/**
 * Manual verification script for account and lifecycle email queueing.
 *
 * This script runs inside a rollback-only transaction by default. It creates or
 * reuses a transaction-local test user so email_outbox.user_id always satisfies
 * the production foreign key. Set ARTSFOLIO_EMAIL_OUTBOX_TEST_COMMIT=1 only
 * with a safe local mailbox or SMTP sink.
 */

use App\Platform\Email\EmailOutboxRepository;
use App\Platform\Email\LifecycleEmailService;
use App\Platform\Email\TemplateRenderer;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$commit = ((string) getenv('ARTSFOLIO_EMAIL_OUTBOX_TEST_COMMIT')) === '1';
$recipientEmail = 'welcome-test@example.test';
$fixtureUserEmail = 'email-outbox-test-user@example.test';

$pdo = Database::connect($root);

$tenantId = (int) ($pdo->query('SELECT id FROM tenants ORDER BY id LIMIT 1')->fetchColumn() ?: 0);

if ($tenantId < 1) {
    fwrite(STDERR, "Missing tenant fixture for email outbox smoke test.\n");
    exit(1);
}

$pdo->beginTransaction();

try {
    $userLookup = $pdo->prepare(
        "SELECT id
         FROM users
         WHERE email = :email
         LIMIT 1"
    );

    $userLookup->execute(['email' => $fixtureUserEmail]);
    $userId = (int) ($userLookup->fetchColumn() ?: 0);

    if ($userId < 1) {
        $insertUser = $pdo->prepare(
            "INSERT INTO users (
                uuid,
                email,
                password_hash,
                display_name,
                email_verified_at
            ) VALUES (
                UUID(),
                :email,
                NULL,
                :display_name,
                CURRENT_TIMESTAMP
            )"
        );

        $insertUser->execute([
            'email' => $fixtureUserEmail,
            'display_name' => 'Email Outbox Test User',
        ]);

        $userId = (int) $pdo->lastInsertId();
    }

    if ($userId < 1) {
        throw new RuntimeException('Could not create or resolve email outbox test user.');
    }

    $outbox = new EmailOutboxRepository($pdo);
    $service = new LifecycleEmailService(
        outbox: $outbox,
        renderer: new TemplateRenderer(),
        templateRoot: $root . '/template',
    );

    $passwordResetId = $service->queuePasswordReset(
        recipientEmail: $recipientEmail,
        resetUrl: 'https://artsfol.io/reset-password/example-token',
        userId: $userId,
    );

    $emailVerificationId = $service->queueEmailVerification(
        recipientEmail: $recipientEmail,
        verificationUrl: 'https://artsfol.io/verify-email/example-token',
        userId: $userId,
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
        tenantId: $tenantId,
        userId: $userId,
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
        'tenant_id' => $tenantId,
        'user_id' => $userId,
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
