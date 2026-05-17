<?php

declare(strict_types=1);

/**
 * Queues lifecycle email rows in email_outbox.
 *
 * This script intentionally writes directly to email_outbox because the current
 * refactor already has a working outbox and worker path. A later cleanup can
 * move this into an application service once the framework boundary settles.
 *
 * Usage:
 *
 *   ARTSFOLIO_ENV_FILE=.env.local php scripts/email/queue_lifecycle_emails.php \
 *     --tenant-slug=bxiie \
 *     --email=artist@example.com \
 *     --name="Artist Name" \
 *     --lifecycle=tenant_admin_onboarding
 */

use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$options = getopt('', [
    'tenant-slug:',
    'email:',
    'name::',
    'user-id::',
    'lifecycle::',
    'dry-run::',
]);

$tenantSlug = trim((string) ($options['tenant-slug'] ?? ''));
$email = strtolower(trim((string) ($options['email'] ?? '')));
$name = trim((string) ($options['name'] ?? ''));
$userId = isset($options['user-id']) ? (int) $options['user-id'] : null;
$lifecycle = trim((string) ($options['lifecycle'] ?? 'tenant_admin_onboarding'));
$dryRun = ((string) ($options['dry-run'] ?? '0')) === '1';

if ($tenantSlug === '' || $email === '') {
    fwrite(STDERR, "Required: --tenant-slug and --email\n");
    exit(1);
}

$pdo = Database::connect($root);

function requireTenantId(PDO $pdo, string $tenantSlug): int
{
    $stmt = $pdo->prepare('SELECT id FROM tenants WHERE slug = :slug LIMIT 1');
    $stmt->execute(['slug' => $tenantSlug]);

    $tenantId = $stmt->fetchColumn();

    if ($tenantId === false) {
        throw new RuntimeException("Tenant not found: {$tenantSlug}");
    }

    return (int) $tenantId;
}

function outboxRowExists(PDO $pdo, int $tenantId, string $email, string $templateKey): bool
{
    $stmt = $pdo->prepare(
        "SELECT id
         FROM email_outbox
         WHERE tenant_id = :tenant_id
           AND recipient_email = :recipient_email
           AND template_key = :template_key
           AND status IN ('queued', 'sending', 'sent')
         LIMIT 1"
    );

    $stmt->execute([
        'tenant_id' => $tenantId,
        'recipient_email' => $email,
        'template_key' => $templateKey,
    ]);

    return (bool) $stmt->fetchColumn();
}

function lifecycleSchedule(string $lifecycle, string $tenantSlug, string $email, string $name): array
{
    $displayName = $name !== '' ? $name : $email;

    return match ($lifecycle) {
        'tenant_admin_onboarding' => [
            [
                'template_key' => 'tenant_admin_welcome_6h',
                'subject' => 'Welcome to ArtsFolio',
                'body_text' => "Welcome to ArtsFolio, {$displayName}.\n\nYour tenant {$tenantSlug} is ready. Sign in at your domain /login URL, review settings, add sections, and test contact/signup forms.",
                'body_html' => "<p>Welcome to ArtsFolio, " . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . ".</p><p>Your tenant <strong>" . htmlspecialchars($tenantSlug, ENT_QUOTES, 'UTF-8') . "</strong> is ready. Sign in at your domain <code>/login</code> URL, review settings, add sections, and test contact/signup forms.</p>",
                'delay_seconds' => 21600,
            ],
            [
                'template_key' => 'tenant_admin_feature_deep_dive_1d',
                'subject' => 'ArtsFolio setup deep dive',
                'body_text' => "Your ArtsFolio tenant is live.\n\nToday: configure homepage copy, portfolio sections, first artwork records, contact delivery, and signup exports.",
                'body_html' => "<p>Your ArtsFolio tenant is live.</p><p>Today: configure homepage copy, portfolio sections, first artwork records, contact delivery, and signup exports.</p>",
                'delay_seconds' => 86400,
            ],
            [
                'template_key' => 'tenant_admin_weekly_checkin',
                'subject' => 'ArtsFolio weekly check-in',
                'body_text' => "Weekly ArtsFolio check-in.\n\nReview contact messages, email signups, artwork updates, stale workers, and domain health.",
                'body_html' => "<p>Weekly ArtsFolio check-in.</p><p>Review contact messages, email signups, artwork updates, stale workers, and domain health.</p>",
                'delay_seconds' => 604800,
            ],
        ],
        'tenant_admin_cancelled' => [
            [
                'template_key' => 'tenant_admin_cancelled_6h',
                'subject' => 'Sorry to see you go',
                'body_text' => "Sorry to see you go.\n\nPlease tell us what would have made ArtsFolio more useful.",
                'body_html' => "<p>Sorry to see you go.</p><p>Please tell us what would have made ArtsFolio more useful.</p>",
                'delay_seconds' => 21600,
            ],
            [
                'template_key' => 'tenant_admin_winback_1w',
                'subject' => 'Would you try ArtsFolio again?',
                'body_text' => "Would you try ArtsFolio again?\n\nWe would like to understand what changed and what would make the platform worth another look.",
                'body_html' => "<p>Would you try ArtsFolio again?</p><p>We would like to understand what changed and what would make the platform worth another look.</p>",
                'delay_seconds' => 604800,
            ],
            [
                'template_key' => 'tenant_admin_winback_1m',
                'subject' => 'One more ArtsFolio check-in',
                'body_text' => "One more ArtsFolio check-in.\n\nIf your needs have changed, we would be glad to help you restart.",
                'body_html' => "<p>One more ArtsFolio check-in.</p><p>If your needs have changed, we would be glad to help you restart.</p>",
                'delay_seconds' => 2592000,
            ],
        ],
        default => throw new InvalidArgumentException("Unsupported lifecycle: {$lifecycle}"),
    };
}

$tenantId = requireTenantId($pdo, $tenantSlug);
$messages = lifecycleSchedule($lifecycle, $tenantSlug, $email, $name);
$queued = [];
$skipped = [];

if (!$dryRun) {
    $pdo->beginTransaction();
}

try {
    foreach ($messages as $message) {
        $templateKey = (string) $message['template_key'];

        if (!$dryRun && outboxRowExists($pdo, $tenantId, $email, $templateKey)) {
            $skipped[] = $templateKey;
            continue;
        }

        $availableAt = date('Y-m-d H:i:s', time() + (int) $message['delay_seconds']);

        if (!$dryRun) {
            $stmt = $pdo->prepare(
                "INSERT INTO email_outbox (
                    tenant_id,
                    user_id,
                    recipient_email,
                    recipient_name,
                    subject,
                    body_text,
                    body_html,
                    template_key,
                    status,
                    available_at,
                    created_at,
                    updated_at
                ) VALUES (
                    :tenant_id,
                    :user_id,
                    :recipient_email,
                    :recipient_name,
                    :subject,
                    :body_text,
                    :body_html,
                    :template_key,
                    'queued',
                    :available_at,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )"
            );

            $stmt->execute([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'recipient_email' => $email,
                'recipient_name' => $name !== '' ? $name : null,
                'subject' => (string) $message['subject'],
                'body_text' => (string) $message['body_text'],
                'body_html' => (string) $message['body_html'],
                'template_key' => $templateKey,
                'available_at' => $availableAt,
            ]);
        }

        $queued[] = [
            'template_key' => $templateKey,
            'available_at' => $availableAt,
        ];
    }

    if (!$dryRun) {
        $pdo->commit();
    }
} catch (Throwable $e) {
    if (!$dryRun) {
        $pdo->rollBack();
    }

    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

echo json_encode([
    'ok' => true,
    'dry_run' => $dryRun,
    'tenant_id' => $tenantId,
    'tenant_slug' => $tenantSlug,
    'email' => $email,
    'lifecycle' => $lifecycle,
    'queued' => $queued,
    'skipped_existing' => $skipped,
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
