<?php

declare(strict_types=1);

/**
 * Reconciles missing tenant-admin onboarding lifecycle messages.
 *
 * Usage:
 *   php scripts/email/reconcile_tenant_lifecycle_emails.php \
 *     --tenant-slug=facebooktest
 *
 * Add --dry-run=1 to report without writing.
 */

use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$options = getopt('', [
    'tenant-slug:',
    'dry-run::',
]);

$tenantSlug = strtolower(trim((string) ($options['tenant-slug'] ?? '')));
$dryRun = ((string) ($options['dry-run'] ?? '0')) === '1';

if ($tenantSlug === '') {
    fwrite(STDERR, "[FAIL] Required: --tenant-slug=<slug>\n");
    exit(1);
}

$pdo = Database::connect($root);

$stmt = $pdo->prepare(
    "SELECT
        t.id AS tenant_id,
        t.slug,
        t.created_at AS tenant_created_at,
        u.id AS user_id,
        u.email,
        COALESCE(NULLIF(u.display_name, ''), u.email) AS display_name,
        r.slug AS role_slug
     FROM tenants t
     JOIN role_assignments ra
       ON ra.tenant_id = t.id
     JOIN roles r
       ON r.id = ra.role_id
      AND r.scope = 'tenant'
      AND r.slug IN ('owner', 'admin')
     JOIN tenant_memberships tm
       ON tm.tenant_id = t.id
      AND tm.user_id = ra.user_id
      AND tm.status = 'active'
     JOIN users u
       ON u.id = ra.user_id
     WHERE t.slug = :slug
     ORDER BY (r.slug = 'owner') DESC, ra.id ASC
     LIMIT 1"
);
$stmt->execute(['slug' => $tenantSlug]);
$owner = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$owner) {
    fwrite(
        STDERR,
        "[FAIL] No active tenant owner/admin found for {$tenantSlug}.\n"
    );
    exit(1);
}

$schedule = [
    ['tenant_admin_welcome_6h', 'Welcome to ArtsFolio', 21600],
    ['tenant_admin_feature_deep_dive_1d', 'ArtsFolio setup deep dive', 86400],
    ['tenant_admin_weekly_checkin', 'ArtsFolio weekly check-in', 604800],
];

$existingStmt = $pdo->prepare(
    "SELECT id, status, available_at, sent_at, last_error
       FROM email_outbox
      WHERE tenant_id = :tenant_id
        AND user_id = :user_id
        AND template_key = :template_key
      ORDER BY id ASC
      LIMIT 1"
);

$insertStmt = $pdo->prepare(
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

$tenantCreated = new DateTimeImmutable(
    (string) $owner['tenant_created_at'],
    new DateTimeZone('UTC'),
);
$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$queued = 0;
$existing = 0;

foreach ($schedule as [$templateKey, $subject, $delaySeconds]) {
    $existingStmt->execute([
        'tenant_id' => (int) $owner['tenant_id'],
        'user_id' => (int) $owner['user_id'],
        'template_key' => $templateKey,
    ]);
    $row = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        ++$existing;
        echo sprintf(
            "[EXISTS] %s id=%d status=%s available_at=%s sent_at=%s error=%s\n",
            $templateKey,
            (int) $row['id'],
            (string) $row['status'],
            (string) $row['available_at'],
            (string) ($row['sent_at'] ?? ''),
            trim((string) ($row['last_error'] ?? '')),
        );
        continue;
    }

    $dueAt = $tenantCreated->modify('+' . $delaySeconds . ' seconds');
    if ($dueAt < $now) {
        $dueAt = $now;
    }

    echo sprintf(
        "[MISSING] %s recipient=%s due=%s%s\n",
        $templateKey,
        (string) $owner['email'],
        $dueAt->format('Y-m-d H:i:s'),
        $dryRun ? ' dry-run' : '',
    );

    if ($dryRun) {
        continue;
    }

    $displayName = (string) $owner['display_name'];
    $bodyText = "ArtsFolio {$templateKey} for tenant {$tenantSlug}.";
    $bodyHtml = '<p>ArtsFolio '
        . htmlspecialchars($templateKey, ENT_QUOTES, 'UTF-8')
        . ' for tenant <strong>'
        . htmlspecialchars($tenantSlug, ENT_QUOTES, 'UTF-8')
        . '</strong>.</p>';

    $insertStmt->execute([
        'tenant_id' => (int) $owner['tenant_id'],
        'user_id' => (int) $owner['user_id'],
        'recipient_email' => (string) $owner['email'],
        'recipient_name' => $displayName,
        'subject' => $subject,
        'body_text' => $bodyText,
        'body_html' => $bodyHtml,
        'template_key' => $templateKey,
        'available_at' => $dueAt->format('Y-m-d H:i:s'),
    ]);
    ++$queued;
}

echo sprintf(
    "[PASS] tenant=%s existing=%d queued=%d dry_run=%s\n",
    $tenantSlug,
    $existing,
    $queued,
    $dryRun ? 'yes' : 'no',
);

// End of file.
