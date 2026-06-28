<?php

declare(strict_types=1);

/**
 * Repairs a canceled/unpaid checkout row where a paid target was activated early.
 *
 * Command:
 *   php scripts/billing/repair_canceled_checkout_entitlement.php --dry-run --tenant=googlesignup
 *   php scripts/billing/repair_canceled_checkout_entitlement.php --apply --tenant=googlesignup
 *
 * Supported CLI options: --dry-run, --apply, --tenant:, --json
 *
 * This command targets rows like:
 * - current local plan is paid
 * - pending paid target is the same paid plan
 * - Stripe checkout session exists
 * - Stripe subscription ID is empty
 * - billing is payment_pending/active/blank or pending_change_type is upgrade/paid_start
 *
 * It restores plan_id to the active Free plan while preserving pending_plan_id
 * as the paid target, sets pending_change_type=paid_start, zeros pending
 * proration, and appends a billing note.
 */

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$config = require $root . '/config/database.php';
$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $config['host'],
    $config['port'],
    $config['database']
);

$pdo = new PDO($dsn, $config['username'], $config['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$options = getopt('', ['dry-run', 'apply', 'tenant:', 'json']);
$dryRun = array_key_exists('dry-run', $options);
$apply = array_key_exists('apply', $options);
$json = array_key_exists('json', $options);
$tenantFilter = isset($options['tenant']) ? trim((string) $options['tenant']) : '';

if (!$dryRun && !$apply) {
    fwrite(STDERR, "[FAIL] Choose --dry-run or --apply.\n");
    exit(2);
}
if ($tenantFilter === '') {
    fwrite(STDERR, "[FAIL] Missing --tenant=slug-or-id\n");
    exit(2);
}

$freePlanId = free_plan_id($pdo);
if ($freePlanId < 1) {
    fwrite(STDERR, "[FAIL] Could not find an active free plan.\n");
    exit(1);
}

$rows = candidate_rows($pdo, $tenantFilter);
$result = [
    'ok' => true,
    'dry_run' => $dryRun,
    'apply' => $apply,
    'tenant' => $tenantFilter,
    'free_plan_id' => $freePlanId,
    'candidate_count' => count($rows),
    'repaired_count' => 0,
    'rows' => $rows,
];

if ($apply && $rows !== []) {
    $stmt = $pdo->prepare(
        'UPDATE tenant_plan_assignments
            SET plan_id = :free_plan_id,
                billing_status = "payment_pending",
                pending_change_type = "paid_start",
                pending_proration_cents = 0,
                billing_note = CONCAT(COALESCE(billing_note, ""), "\nRepaired canceled/unpaid Stripe Checkout: active entitlement restored to Free; pending paid target preserved; proration cleared.")
          WHERE id = :id
            AND plan_id <> :free_plan_id
            AND (stripe_subscription_id IS NULL OR stripe_subscription_id = "")'
    );

    foreach ($rows as $row) {
        $stmt->execute([
            'free_plan_id' => $freePlanId,
            'id' => (int) $row['assignment_id'],
        ]);
        $result['repaired_count'] += $stmt->rowCount() > 0 ? 1 : 0;
    }
}

if ($json) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

if ($rows === []) {
    echo "[PASS] No canceled/unpaid checkout entitlement rows found for {$tenantFilter}.\n";
} else {
    foreach ($rows as $row) {
        echo '[WARN] tenant=' . $row['tenant_slug']
            . ' assignment_id=' . $row['assignment_id']
            . ' current_plan=' . $row['current_plan_slug']
            . ' pending_plan=' . $row['pending_plan_slug']
            . ' billing_status=' . ($row['billing_status'] ?? '')
            . ' pending_change_type=' . ($row['pending_change_type'] ?? '')
            . ' pending_proration_cents=' . ($row['pending_proration_cents'] ?? '')
            . ' checkout_session=' . ($row['stripe_checkout_session_id'] ?? '')
            . ' subscription=' . ($row['stripe_subscription_id'] ?? '')
            . "\n";
    }

    echo $apply
        ? "[PASS] Repaired {$result['repaired_count']} canceled/unpaid checkout entitlement row(s).\n"
        : "[INFO] Dry run only. Re-run with --apply to restore active plan_id to Free.\n";
}

exit(0);

/** @return list<array<string,mixed>> */
function candidate_rows(PDO $pdo, string $tenantFilter): array
{
    $sql = 'SELECT
                tpa.id AS assignment_id,
                t.id AS tenant_id,
                t.slug AS tenant_slug,
                t.name AS tenant_name,
                tpa.plan_id,
                tpa.pending_plan_id,
                tpa.billing_status,
                tpa.pending_change_type,
                tpa.pending_proration_cents,
                tpa.stripe_checkout_session_id,
                tpa.stripe_subscription_id,
                tpa.billing_note,
                current_plan.slug AS current_plan_slug,
                current_plan.name AS current_plan_name,
                current_plan.monthly_price_cents AS current_plan_monthly_price_cents,
                pending_plan.slug AS pending_plan_slug,
                pending_plan.name AS pending_plan_name,
                pending_plan.monthly_price_cents AS pending_plan_monthly_price_cents
            FROM tenant_plan_assignments tpa
            JOIN tenants t ON t.id = tpa.tenant_id
            JOIN plans current_plan ON current_plan.id = tpa.plan_id
            LEFT JOIN plans pending_plan ON pending_plan.id = tpa.pending_plan_id
            WHERE (t.slug = :tenant_filter OR CAST(t.id AS CHAR) = :tenant_filter)
              AND current_plan.monthly_price_cents > 0
              AND (tpa.stripe_subscription_id IS NULL OR tpa.stripe_subscription_id = "")
              AND (tpa.stripe_checkout_session_id IS NOT NULL AND tpa.stripe_checkout_session_id <> "")
              AND (
                    tpa.pending_plan_id IS NULL
                 OR tpa.pending_plan_id = tpa.plan_id
                 OR pending_plan.monthly_price_cents > 0
              )
              AND (
                    tpa.billing_status IN ("payment_pending", "active")
                 OR tpa.billing_status IS NULL
                 OR tpa.billing_status = ""
                 OR tpa.pending_change_type IN ("upgrade", "paid_start")
              )
            ORDER BY tpa.id ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['tenant_filter' => $tenantFilter]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function free_plan_id(PDO $pdo): int
{
    $stmt = $pdo->query(
        'SELECT id
           FROM plans
          WHERE is_active = 1
            AND monthly_price_cents = 0
          ORDER BY CASE WHEN slug = "free" THEN 0 ELSE 1 END, id ASC
          LIMIT 1'
    );

    return (int) $stmt->fetchColumn();
}

// End of file.
