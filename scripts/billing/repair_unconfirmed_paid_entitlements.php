<?php

declare(strict_types=1);

/**
 * Broadly repairs paid local entitlements that do not have Stripe confirmation.
 *
 * Command:
 * Supported CLI options: --dry-run, --apply, --tenant:, --json, --include-active
 *   php scripts/billing/repair_unconfirmed_paid_entitlements.php --dry-run
 *   php scripts/billing/repair_unconfirmed_paid_entitlements.php --apply
 *
 * This is broader than repair_unpaid_paid_start_entitlements.php. It finds
 * tenants whose current plan is paid but whose assignment has no confirmed
 * Stripe subscription ID. By default it only includes rows with checkout-style
 * evidence: payment_pending, paid_start/upgrade pending state, checkout session
 * ID, or a missing billing_status on a paid plan. Use --include-active to also
 * include active paid rows without subscription IDs.
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

$options = getopt('', ['dry-run', 'apply', 'tenant:', 'json', 'include-active']);
$dryRun = array_key_exists('dry-run', $options);
$apply = array_key_exists('apply', $options);
$json = array_key_exists('json', $options);
$includeActive = array_key_exists('include-active', $options);
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

$columns = table_columns($pdo, 'tenant_plan_assignments');
$rows = candidate_rows($pdo, $tenantFilter, $includeActive, $columns);

$result = [
    'ok' => true,
    'dry_run' => $dryRun,
    'apply' => $apply,
    'include_active' => $includeActive,
    'free_plan_id' => $freePlanId,
    'candidate_count' => count($rows),
    'repaired_count' => 0,
    'rows' => $rows,
];

if ($apply && $rows !== []) {
    $setParts = ['plan_id = :free_plan_id'];
    if (in_array('billing_status', $columns, true)) {
        $setParts[] = 'billing_status = "payment_pending"';
    }
    if (in_array('pending_plan_id', $columns, true)) {
        $setParts[] = 'pending_plan_id = CASE WHEN pending_plan_id IS NULL THEN plan_id ELSE pending_plan_id END';
    }
    if (in_array('pending_change_type', $columns, true)) {
        $setParts[] = 'pending_change_type = CASE WHEN pending_change_type IS NULL OR pending_change_type = "" THEN "paid_start" ELSE pending_change_type END';
    }
    if (in_array('billing_note', $columns, true)) {
        $setParts[] = 'billing_note = CONCAT(COALESCE(billing_note, ""), "\nRepaired unconfirmed paid checkout entitlement: restored active plan to Free while preserving pending paid target.")';
    }

    $sql = 'UPDATE tenant_plan_assignments
               SET ' . implode(', ', $setParts) . '
             WHERE id = :id
               AND plan_id <> :free_plan_id
               AND (stripe_subscription_id IS NULL OR stripe_subscription_id = "")';

    $stmt = $pdo->prepare($sql);

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
        echo "[PASS] No unconfirmed paid entitlement rows found.\n";
    } else {
        foreach ($rows as $row) {
            echo '[WARN] tenant=' . $row['tenant_slug']
                . ' assignment_id=' . $row['assignment_id']
                . ' current_plan=' . $row['current_plan_slug']
                . ' billing_status=' . ($row['billing_status'] ?? '')
                . ' pending_change_type=' . ($row['pending_change_type'] ?? '')
                . ' pending_plan=' . ($row['pending_plan_slug'] ?? '')
                . ' checkout_session=' . ($row['stripe_checkout_session_id'] ?? '')
                . ' subscription=' . ($row['stripe_subscription_id'] ?? '')
                . "\n";
        }
        echo $apply
            ? "[PASS] Repaired {$result['repaired_count']} unconfirmed paid entitlement row(s).\n"
            : "[INFO] Dry run only. Re-run with --apply to restore active plan_id to Free.\n";
    }
}

exit(0);

/** @return list<array<string,mixed>> */
function candidate_rows(PDO $pdo, string $tenantFilter, bool $includeActive, array $columns): array
{
    $select = [
        'tpa.id AS assignment_id',
        't.id AS tenant_id',
        't.slug AS tenant_slug',
        't.name AS tenant_name',
        'current_plan.slug AS current_plan_slug',
        'current_plan.name AS current_plan_name',
        'current_plan.monthly_price_cents AS current_plan_monthly_price_cents',
    ];

    foreach ([
        'billing_status',
        'pending_change_type',
        'pending_plan_id',
        'stripe_checkout_session_id',
        'stripe_subscription_id',
        'stripe_customer_id',
    ] as $column) {
        $select[] = in_array($column, $columns, true) ? 'tpa.' . $column : 'NULL AS ' . $column;
    }

    $joins = [
        'JOIN tenants t ON t.id = tpa.tenant_id',
        'JOIN plans current_plan ON current_plan.id = tpa.plan_id',
    ];

    if (in_array('pending_plan_id', $columns, true)) {
        $select[] = 'pending_plan.slug AS pending_plan_slug';
        $select[] = 'pending_plan.name AS pending_plan_name';
        $joins[] = 'LEFT JOIN plans pending_plan ON pending_plan.id = tpa.pending_plan_id';
    } else {
        $select[] = 'NULL AS pending_plan_slug';
        $select[] = 'NULL AS pending_plan_name';
    }

    $where = [
        'current_plan.monthly_price_cents > 0',
        '(tpa.stripe_subscription_id IS NULL OR tpa.stripe_subscription_id = "")',
    ];
    $params = [];

    $evidence = [];
    if (in_array('billing_status', $columns, true)) {
        $evidence[] = 'tpa.billing_status = "payment_pending"';
        $evidence[] = 'tpa.billing_status IS NULL';
        $evidence[] = 'tpa.billing_status = ""';
        if ($includeActive) {
            $evidence[] = 'tpa.billing_status = "active"';
        }
    }
    if (in_array('pending_change_type', $columns, true)) {
        $evidence[] = 'tpa.pending_change_type IN ("paid_start", "upgrade")';
    }
    if (in_array('stripe_checkout_session_id', $columns, true)) {
        $evidence[] = '(tpa.stripe_checkout_session_id IS NOT NULL AND tpa.stripe_checkout_session_id <> "")';
    }
    if (in_array('pending_plan_id', $columns, true)) {
        $evidence[] = 'tpa.pending_plan_id IS NOT NULL';
    }

    if ($evidence !== []) {
        $where[] = '(' . implode(' OR ', $evidence) . ')';
    }

    if ($tenantFilter !== '') {
        $where[] = '(t.slug = :tenant_filter OR CAST(t.id AS CHAR) = :tenant_filter)';
        $params['tenant_filter'] = $tenantFilter;
    }

    $sql = 'SELECT ' . implode(', ', $select) . '
            FROM tenant_plan_assignments tpa
            ' . implode("\n            ", $joins) . '
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

/** @return list<string> */
function table_columns(PDO $pdo, string $table): array
{
    $stmt = $pdo->prepare(
        'SELECT column_name
           FROM information_schema.columns
          WHERE table_schema = DATABASE()
            AND table_name = :table'
    );
    $stmt->execute(['table' => $table]);

    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

// End of file.
