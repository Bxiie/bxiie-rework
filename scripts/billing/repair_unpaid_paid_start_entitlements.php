<?php

declare(strict_types=1);

/**
 * Repairs unpaid paid-start rows that accidentally activated paid plan access.
 *
 * Command:
 *   php scripts/billing/repair_unpaid_paid_start_entitlements.php --dry-run
 *   php scripts/billing/repair_unpaid_paid_start_entitlements.php --apply
 *
 * The repair only targets rows that are still payment_pending, have a
 * pending_change_type of paid_start, have no Stripe subscription ID, and have
 * plan_id equal to pending_plan_id for a paid plan. Those rows are moved back
 * to the active Free plan while preserving the pending paid target.
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
    'free_plan_id' => $freePlanId,
    'candidate_count' => count($rows),
    'repaired_count' => 0,
    'rows' => $rows,
];

if ($apply && $rows !== []) {
    $stmt = $pdo->prepare(
        'UPDATE tenant_plan_assignments
            SET plan_id = :free_plan_id,
                billing_note = CONCAT(COALESCE(billing_note, ""), "\nRepaired unpaid paid-start checkout: entitlement restored to Free while pending paid target remains.")
          WHERE id = :id
            AND billing_status = "payment_pending"
            AND pending_change_type = "paid_start"
            AND (stripe_subscription_id IS NULL OR stripe_subscription_id = "")
            AND plan_id = pending_plan_id'
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
} else {
    if ($rows === []) {
        echo "[PASS] No unpaid paid-start entitlement rows found.\n";
    } else {
        foreach ($rows as $row) {
            echo '[WARN] tenant=' . $row['tenant_slug']
                . ' assignment_id=' . $row['assignment_id']
                . ' current_plan=' . $row['current_plan_slug']
                . ' pending_plan=' . $row['pending_plan_slug']
                . ' checkout_session=' . ($row['stripe_checkout_session_id'] ?? '')
                . "\n";
        }
        echo $apply
            ? "[PASS] Repaired {$result['repaired_count']} unpaid paid-start entitlement row(s).\n"
            : "[INFO] Dry run only. Re-run with --apply to restore plan_id to Free.\n";
    }
}

exit(0);

/** @return list<array<string,mixed>> */
function candidate_rows(PDO $pdo, string $tenantFilter): array
{
    $where = [
        'tpa.billing_status = "payment_pending"',
        'tpa.pending_change_type = "paid_start"',
        '(tpa.stripe_subscription_id IS NULL OR tpa.stripe_subscription_id = "")',
        'tpa.plan_id = tpa.pending_plan_id',
        'current_plan.monthly_price_cents > 0',
    ];
    $params = [];

    if ($tenantFilter !== '') {
        $where[] = '(t.slug = :tenant_filter OR CAST(t.id AS CHAR) = :tenant_filter)';
        $params['tenant_filter'] = $tenantFilter;
    }

    $sql = 'SELECT
                tpa.id AS assignment_id,
                t.id AS tenant_id,
                t.slug AS tenant_slug,
                t.name AS tenant_name,
                current_plan.slug AS current_plan_slug,
                current_plan.name AS current_plan_name,
                pending_plan.slug AS pending_plan_slug,
                pending_plan.name AS pending_plan_name,
                tpa.billing_status,
                tpa.pending_change_type,
                tpa.stripe_checkout_session_id,
                tpa.stripe_subscription_id
            FROM tenant_plan_assignments tpa
            JOIN tenants t ON t.id = tpa.tenant_id
            JOIN plans current_plan ON current_plan.id = tpa.plan_id
            JOIN plans pending_plan ON pending_plan.id = tpa.pending_plan_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY tpa.id ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

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
