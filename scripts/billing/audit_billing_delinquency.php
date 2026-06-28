<?php

declare(strict_types=1);

/**
 * Read-only billing delinquency audit.
 *
 * Lists tenants with past-due/unpaid subscriptions and classifies them using
 * BillingDelinquencyPolicy. This command does not mutate tenant state.
 */

use App\Platform\Billing\BillingDelinquencyPolicy;

$root = dirname(__DIR__, 2);

require $root . '/bootstrap/app.php';

$configFile = $root . '/config/database.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "[FAIL] Missing config/database.php\n");
    exit(1);
}

$config = require $configFile;
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

$options = getopt('', ['json', 'strict']);
$json = array_key_exists('json', $options);
$strict = array_key_exists('strict', $options);

if (!table_exists($pdo, 'tenant_plan_assignments')) {
    finish([
        'ok' => false,
        'critical_count' => 1,
        'warning_count' => 0,
        'rows' => [],
        'problems' => [[
            'severity' => 'CRIT',
            'code' => 'missing_tenant_plan_assignments',
            'message' => 'tenant_plan_assignments table is missing.',
        ]],
    ], $json, $strict);
}

$columns = table_columns($pdo, 'tenant_plan_assignments');
$requiredColumns = ['billing_status', 'billing_action_required_at'];
$missing = array_values(array_diff($requiredColumns, $columns));
if ($missing !== []) {
    finish([
        'ok' => false,
        'critical_count' => 0,
        'warning_count' => count($missing),
        'rows' => [],
        'problems' => array_map(
            static fn (string $column): array => [
                'severity' => 'WARN',
                'code' => 'missing_column',
                'message' => 'tenant_plan_assignments.' . $column . ' is missing; run billing migrations before auditing delinquency.',
            ],
            $missing,
        ),
    ], $json, $strict);
}

$select = [
    't.id AS tenant_id',
    't.slug AS tenant_slug',
    't.name AS tenant_name',
    'p.slug AS plan_slug',
    'p.name AS plan_name',
    'tpa.billing_status',
    'tpa.billing_action_required_at',
];

foreach ([
    'last_payment_failed_at',
    'latest_invoice_url',
    'latest_invoice_number',
    'current_period_ends_at',
    'stripe_customer_id',
    'stripe_subscription_id',
] as $column) {
    $select[] = in_array($column, $columns, true) ? 'tpa.' . $column : 'NULL AS ' . $column;
}

$orderBy = in_array('billing_action_required_at', $columns, true)
    ? 'tpa.billing_action_required_at ASC, tpa.id ASC'
    : 'tpa.id ASC';

$sql = 'SELECT ' . implode(', ', $select) . '
          FROM tenant_plan_assignments tpa
          JOIN tenants t ON t.id = tpa.tenant_id
          JOIN plans p ON p.id = tpa.plan_id
         WHERE tpa.billing_status IN (\'past_due\', \'unpaid\')
         ORDER BY ' . $orderBy;

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

$classified = [];
$critical = 0;
$warnings = 0;

foreach ($rows as $row) {
    $actionRequiredAt = $row['billing_action_required_at'] !== null ? (string) $row['billing_action_required_at'] : null;
    $state = BillingDelinquencyPolicy::stateFor((string) ($row['billing_status'] ?? ''), $actionRequiredAt);
    $severity = BillingDelinquencyPolicy::severityForState($state);

    if ($severity === 'CRIT') {
        $critical += 1;
    } elseif ($severity === 'WARN') {
        $warnings += 1;
    }

    $classified[] = [
        'severity' => $severity,
        'state' => $state,
        'state_label' => BillingDelinquencyPolicy::labelForState($state),
        'action' => BillingDelinquencyPolicy::actionForState($state),
        'age_days' => BillingDelinquencyPolicy::ageDays($actionRequiredAt),
        'grace_ends_at' => BillingDelinquencyPolicy::graceEndsAt($actionRequiredAt),
        'restriction_begins_at' => BillingDelinquencyPolicy::restrictionBeginsAt($actionRequiredAt),
        'final_review_at' => BillingDelinquencyPolicy::finalReviewAt($actionRequiredAt),
        'tenant_id' => (int) $row['tenant_id'],
        'tenant_slug' => (string) $row['tenant_slug'],
        'tenant_name' => (string) $row['tenant_name'],
        'plan_slug' => (string) $row['plan_slug'],
        'plan_name' => (string) $row['plan_name'],
        'billing_status' => (string) $row['billing_status'],
        'billing_action_required_at' => $row['billing_action_required_at'],
        'last_payment_failed_at' => $row['last_payment_failed_at'] ?? null,
        'latest_invoice_url' => $row['latest_invoice_url'] ?? null,
        'latest_invoice_number' => $row['latest_invoice_number'] ?? null,
        'current_period_ends_at' => $row['current_period_ends_at'] ?? null,
        'stripe_customer_id' => $row['stripe_customer_id'] ?? null,
        'stripe_subscription_id' => $row['stripe_subscription_id'] ?? null,
    ];
}

finish([
    'ok' => $critical === 0 && (!$strict || $warnings === 0),
    'critical_count' => $critical,
    'warning_count' => $warnings,
    'rows' => $classified,
    'problems' => [],
], $json, $strict);

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

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
           FROM information_schema.tables
          WHERE table_schema = DATABASE()
            AND table_name = :table'
    );
    $stmt->execute(['table' => $table]);

    return (int) $stmt->fetchColumn() > 0;
}

/**
 * @param array{ok:bool,critical_count:int,warning_count:int,rows:list<array<string,mixed>>,problems:list<array<string,mixed>>} $result
 */
function finish(array $result, bool $json, bool $strict): never
{
    if ($json) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        if ($result['rows'] === [] && $result['problems'] === []) {
            echo "[PASS] Billing delinquency audit passed. No past-due or unpaid tenants found.\n";
        }

        foreach ($result['problems'] as $problem) {
            echo '[' . $problem['severity'] . '] ' . $problem['code'] . ' | ' . $problem['message'] . "\n";
        }

        foreach ($result['rows'] as $row) {
            echo '[' . $row['severity'] . '] '
                . $row['tenant_slug']
                . ' | ' . $row['state_label']
                . ' | age_days=' . $row['age_days']
                . ' | plan=' . $row['plan_slug']
                . ' | status=' . $row['billing_status']
                . ' | action=' . $row['action']
                . "\n";
        }
    }

    exit($result['critical_count'] > 0 || ($strict && $result['warning_count'] > 0) ? 1 : 0);
}

// End of file.
