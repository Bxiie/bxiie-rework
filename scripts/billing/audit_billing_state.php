<?php

declare(strict_types=1);

/**
 * Audits billing status values and pending-change state for schema drift.
 *
 * This command is read-only. It is intended for post-deploy checks and incident
 * triage after billing migration or Stripe webhook changes.
 */

use App\Platform\Billing\BillingStatus;

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

$problems = [];

if (!table_exists($pdo, 'tenant_plan_assignments')) {
    $problems[] = [
        'severity' => 'CRIT',
        'code' => 'missing_tenant_plan_assignments',
        'message' => 'tenant_plan_assignments table is missing.',
        'count' => 1,
    ];
    finish($problems, $json, $strict);
}

$columns = table_columns($pdo, 'tenant_plan_assignments');

if (in_array('billing_status', $columns, true)) {
    $allowed = BillingStatus::billingStatuses();
    $placeholders = implode(',', array_fill(0, count($allowed), '?'));
    $stmt = $pdo->prepare(
        "SELECT billing_status, COUNT(*) AS total
           FROM tenant_plan_assignments
          WHERE billing_status IS NOT NULL
            AND billing_status <> ''
            AND billing_status NOT IN ({$placeholders})
          GROUP BY billing_status
          ORDER BY total DESC, billing_status ASC"
    );
    $stmt->execute($allowed);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $problems[] = [
            'severity' => 'CRIT',
            'code' => 'unknown_billing_status',
            'message' => 'Unknown billing_status value: ' . (string) $row['billing_status'],
            'count' => (int) $row['total'],
        ];
    }
} else {
    $problems[] = [
        'severity' => 'WARN',
        'code' => 'missing_billing_status_column',
        'message' => 'tenant_plan_assignments.billing_status is missing; run subscription billing migrations.',
        'count' => 1,
    ];
}

if (in_array('pending_change_type', $columns, true)) {
    $allowed = BillingStatus::pendingChangeTypes();
    $placeholders = implode(',', array_fill(0, count($allowed), '?'));
    $stmt = $pdo->prepare(
        "SELECT pending_change_type, COUNT(*) AS total
           FROM tenant_plan_assignments
          WHERE pending_change_type IS NOT NULL
            AND pending_change_type <> ''
            AND pending_change_type NOT IN ({$placeholders})
          GROUP BY pending_change_type
          ORDER BY total DESC, pending_change_type ASC"
    );
    $stmt->execute($allowed);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $problems[] = [
            'severity' => 'CRIT',
            'code' => 'unknown_pending_change_type',
            'message' => 'Unknown pending_change_type value: ' . (string) $row['pending_change_type'],
            'count' => (int) $row['total'],
        ];
    }

    if (in_array('pending_effective_at', $columns, true)) {
        $count = scalar_int(
            $pdo,
            "SELECT COUNT(*)
               FROM tenant_plan_assignments
              WHERE pending_change_type IS NOT NULL
                AND pending_change_type <> ''
                AND pending_effective_at IS NULL"
        );
        if ($count > 0) {
            $problems[] = [
                'severity' => 'CRIT',
                'code' => 'pending_change_missing_effective_at',
                'message' => 'Pending billing changes without pending_effective_at.',
                'count' => $count,
            ];
        }
    }

    if (in_array('pending_plan_id', $columns, true)) {
        $count = scalar_int(
            $pdo,
            "SELECT COUNT(*)
               FROM tenant_plan_assignments
              WHERE pending_change_type IN ('upgrade', 'downgrade')
                AND pending_plan_id IS NULL"
        );
        if ($count > 0) {
            $problems[] = [
                'severity' => 'CRIT',
                'code' => 'pending_plan_change_missing_plan_id',
                'message' => 'Pending upgrade/downgrade rows without pending_plan_id.',
                'count' => $count,
            ];
        }
    }
} else {
    $problems[] = [
        'severity' => 'WARN',
        'code' => 'missing_pending_change_type_column',
        'message' => 'tenant_plan_assignments.pending_change_type is missing; scheduled billing changes cannot be audited.',
        'count' => 1,
    ];
}

if (
    in_array('billing_status', $columns, true)
    && in_array('stripe_subscription_id', $columns, true)
    && table_exists($pdo, 'plans')
) {
    $count = scalar_int(
        $pdo,
        "SELECT COUNT(*)
           FROM tenant_plan_assignments
          WHERE billing_status IN ('active', 'past_due', 'unpaid')
            AND (stripe_subscription_id IS NULL OR stripe_subscription_id = '')
            AND plan_id IN (SELECT id FROM plans WHERE monthly_price_cents > 0)"
    );
    if ($count > 0) {
        $problems[] = [
            'severity' => 'WARN',
            'code' => 'paid_billing_status_missing_subscription',
            'message' => 'Paid active/past-due/unpaid assignments without stripe_subscription_id.',
            'count' => $count,
        ];
    }
}

finish($problems, $json, $strict);

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

function scalar_int(PDO $pdo, string $sql): int
{
    return (int) $pdo->query($sql)->fetchColumn();
}

/**
 * @param list<array{severity:string,code:string,message:string,count:int}> $problems
 */
function finish(array $problems, bool $json, bool $strict): never
{
    $critical = 0;
    $warnings = 0;
    foreach ($problems as $problem) {
        if ($problem['severity'] === 'CRIT') {
            $critical += 1;
        } else {
            $warnings += 1;
        }
    }

    if ($json) {
        echo json_encode([
            'ok' => $critical === 0 && (!$strict || $warnings === 0),
            'critical_count' => $critical,
            'warning_count' => $warnings,
            'problems' => $problems,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        if ($problems === []) {
            echo "[PASS] Billing state audit passed.\n";
        } else {
            foreach ($problems as $problem) {
                echo '[' . $problem['severity'] . '] ' . $problem['code'] . ' | count=' . $problem['count'] . ' | ' . $problem['message'] . "\n";
            }
        }
    }

    exit($critical > 0 || ($strict && $warnings > 0) ? 1 : 0);
}

// End of file.
