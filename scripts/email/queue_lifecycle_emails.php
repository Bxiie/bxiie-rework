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
use App\Platform\Email\BrandedEmail;
use App\Platform\Email\EditableEmailTemplate;
use App\Platform\Email\TemplateRenderer;

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

function lifecycleSchedule(string $lifecycle, string $tenantSlug, string $email, string $name, string $root): array
{
    $platformUrl = rtrim((string) (getenv('ARTSFOLIO_PUBLIC_URL') ?: 'https://artsfol.io'), '/');
    $tenantBaseUrl = 'https://' . $tenantSlug . '.artsfol.io';
    $values = [
        'recipient_name' => $name !== '' ? $name : $email,
        'tenant_slug' => $tenantSlug,
        'admin_url' => $tenantBaseUrl . '/admin',
        'tour_url' => $tenantBaseUrl . '/admin/getting-started',
        'help_url' => $platformUrl . '/help',
        'functions_url' => $platformUrl . '/help/tenant-admin-functions',
        'videos_url' => $platformUrl . '/help/training-videos',
    ];
    $renderer = new EditableEmailTemplate(new TemplateRenderer(), $root . '/template/email');

    $definitions = match ($lifecycle) {
        'tenant_admin_onboarding' => [
            ['tenant_admin_welcome_6h', 'lifecycle/tenant_admin_welcome_6h.txt', 21600],
            ['tenant_admin_feature_deep_dive_1d', 'lifecycle/tenant_admin_feature_deep_dive_1d.txt', 86400],
            ['tenant_admin_weekly_checkin', 'lifecycle/tenant_admin_weekly_checkin.txt', 604800],
        ],
        'tenant_admin_cancelled' => [
            ['tenant_admin_cancelled_6h', 'lifecycle/tenant_admin_cancelled_6h.txt', 21600],
            ['tenant_admin_winback_1w', 'lifecycle/tenant_admin_winback_1w.txt', 604800],
            ['tenant_admin_winback_1m', 'lifecycle/tenant_admin_winback_1m.txt', 2592000],
        ],
        default => throw new InvalidArgumentException("Unsupported lifecycle: {$lifecycle}"),
    };

    $messages = [];
    foreach ($definitions as [$templateKey, $templatePath, $delaySeconds]) {
        $message = $renderer->render($templatePath, $values);
        $bodies = BrandedEmail::render($message['subject'], $message['body']);
        $messages[] = [
            'template_key' => $templateKey,
            'subject' => $message['subject'],
            'body_text' => $bodies['body_text'],
            'body_html' => $bodies['body_html'],
            'delay_seconds' => $delaySeconds,
        ];
    }

    return $messages;
}

$tenantId = requireTenantId($pdo, $tenantSlug);
$messages = lifecycleSchedule($lifecycle, $tenantSlug, $email, $name, $root);
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

        $availableAt = gmdate('Y-m-d H:i:s', time() + (int) $message['delay_seconds']);

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
                    UTC_TIMESTAMP(),
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
