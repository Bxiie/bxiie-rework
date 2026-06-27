<?php

declare(strict_types=1);

/**
 * Applies scheduled end-of-period subscription plan changes.
 *
 * With stable Stripe Price IDs configured, due downgrades update the Stripe
 * subscription item to the target plan's monthly Price ID using no proration.
 * Due cancellations cancel the Stripe subscription before moving the tenant to
 * Free locally.
 */

$root = dirname(__DIR__, 2);

require $root . '/bootstrap/app.php';

$config = require $root . '/config/database.php';

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $config['host'],
    (int) $config['port'],
    $config['database']
);

$pdo = new PDO($dsn, $config['username'], $config['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$dryRun = in_array('--dry-run', $argv, true);
$limit = 100;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, min(1000, (int) substr($arg, strlen('--limit='))));
    }
    if ($arg === '-h' || $arg === '--help') {
        echo "Usage: php scripts/billing/apply_pending_subscription_changes.php [--dry-run] [--limit=N]
";
        exit(0);
    }
}

if (!tableExists($pdo, 'tenant_plan_assignments')) {
    fwrite(STDERR, "[FAIL] tenant_plan_assignments table does not exist.
");
    exit(1);
}

$freePlanId = freePlanId($pdo);
$stripeSecretKey = platformSetting($pdo, 'stripe_secret_key');

$stmt = $pdo->prepare(
    'SELECT tpa.id,
            tpa.tenant_id,
            tpa.plan_id,
            tpa.pending_plan_id,
            tpa.pending_plan_slug,
            tpa.pending_change_type,
            tpa.pending_effective_at,
            tpa.stripe_subscription_id,
            tpa.stripe_subscription_item_id,
            p.slug AS current_plan_slug,
            target.stripe_monthly_price_id AS target_stripe_monthly_price_id
       FROM tenant_plan_assignments tpa
       JOIN plans p ON p.id = tpa.plan_id
       LEFT JOIN plans target ON target.id = tpa.pending_plan_id
      WHERE tpa.pending_change_type IN ("downgrade", "cancel")
        AND tpa.pending_effective_at IS NOT NULL
        AND tpa.pending_effective_at <= UTC_TIMESTAMP()
        AND (tpa.pending_change_applied_at IS NULL)
      ORDER BY tpa.pending_effective_at ASC, tpa.id ASC
      LIMIT ' . (int) $limit
);

$stmt->execute();
$rows = $stmt->fetchAll();

if ($rows === []) {
    echo "[PASS] No pending subscription plan changes are due.
";
    exit(0);
}

$applied = 0;
$billing = new \App\Platform\Billing\StripeSubscriptionCheckoutService();

foreach ($rows as $row) {
    $changeType = (string) $row['pending_change_type'];
    $targetPlanId = $changeType === 'cancel' ? $freePlanId : max(1, (int) ($row['pending_plan_id'] ?? 0));
    if ($targetPlanId < 1) {
        fwrite(STDERR, "[WARN] Skipping assignment {$row['id']}: no valid target plan.
");
        continue;
    }
    printf("[INFO] %s tenant_id=%d assignment_id=%d current=%s target_plan_id=%d effective=%s
", $dryRun ? 'Would apply' : 'Applying', (int) $row['tenant_id'], (int) $row['id'], (string) $row['current_plan_slug'], $targetPlanId, (string) $row['pending_effective_at']);
    if ($dryRun) { continue; }

    if ($stripeSecretKey !== '' && trim((string) ($row['stripe_subscription_id'] ?? '')) !== '') {
        try {
            if ($changeType === 'cancel') {
                $billing->cancelSubscriptionNow($stripeSecretKey, (string) $row['stripe_subscription_id']);
            } elseif (trim((string) ($row['stripe_subscription_item_id'] ?? '')) !== '' && trim((string) ($row['target_stripe_monthly_price_id'] ?? '')) !== '') {
                $billing->updateSubscriptionPrice($stripeSecretKey, (string) $row['stripe_subscription_id'], (string) $row['stripe_subscription_item_id'], (string) $row['target_stripe_monthly_price_id'], ['artsfolio_billing_plan_id' => (string) $targetPlanId, 'artsfolio_billing_plan_slug' => (string) ($row['pending_plan_slug'] ?? '')], 'none');
            } else {
                fwrite(STDERR, "[WARN] Stripe downgrade skipped for assignment {$row['id']}: missing subscription item or target Price ID. Local plan change will still apply.
");
            }
        } catch (Throwable $e) {
            fwrite(STDERR, "[FAIL] Stripe update failed for assignment {$row['id']}: {$e->getMessage()}
");
            continue;
        }
    }

    $update = $pdo->prepare('UPDATE tenant_plan_assignments SET plan_id = :plan_id, status = "active", billing_status = CASE WHEN :change_type = "cancel" THEN "canceled" ELSE "active" END, pending_change_applied_at = UTC_TIMESTAMP(), pending_plan_id = NULL, pending_plan_slug = NULL, pending_change_type = NULL, pending_effective_at = NULL, pending_proration_cents = 0, cancel_at_period_end = 0, billing_action_required_at = NULL, billing_note = :billing_note WHERE id = :id AND pending_change_applied_at IS NULL');
    $update->execute(['plan_id' => $targetPlanId, 'change_type' => $changeType, 'billing_note' => $changeType === 'cancel' ? 'Scheduled cancellation applied locally at recurrence date. Stripe subscription cancellation was requested and tenant is now on the Free plan.' : 'Scheduled downgrade applied at recurrence date using stable Stripe Price ID when available.', 'id' => (int) $row['id']]);
    $applied += $update->rowCount() > 0 ? 1 : 0;
}

printf("[PASS] Applied %d pending subscription plan change%s.
", $applied, $applied === 1 ? '' : 's');

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
    $stmt->execute(['table' => $table]);
    return (int) $stmt->fetchColumn() > 0;
}

function freePlanId(PDO $pdo): int
{
    $stmt = $pdo->query('SELECT id FROM plans WHERE slug = "free" LIMIT 1');
    $id = (int) $stmt->fetchColumn();
    if ($id < 1) { fwrite(STDERR, "[FAIL] Could not find the Free plan.
"); exit(1); }
    return $id;
}

function platformSetting(PDO $pdo, string $key): string
{
    if (!tableExists($pdo, 'platform_settings')) { return ''; }
    $stmt = $pdo->prepare('SELECT setting_value FROM platform_settings WHERE setting_key = :setting_key LIMIT 1');
    $stmt->execute(['setting_key' => $key]);
    $value = $stmt->fetchColumn();
    return $value === false ? '' : trim((string) $value);
}

// End of file.
