<?php

declare(strict_types=1);

/**
 * Command: scripts/billing/send_billing_delinquency_report.php
 * Supported CLI options: --dry-run, --force, --json, --quiet
 * Sends a daily billing delinquency report to platform owner/admin users.
 *
 * The report is queued through email_outbox, then delivered by the existing
 * ArtsFolio email worker. By default, the command will not queue duplicate
 * reports for the same UTC day unless --force is supplied.
 */

use App\Platform\Billing\BillingDelinquencyPolicy;
use App\Platform\Email\EmailOutboxRepository;
use App\Platform\Email\TemplateRenderer;

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

$options = getopt('', ['dry-run', 'force', 'json', 'quiet']);
$dryRun = array_key_exists('dry-run', $options);
$force = array_key_exists('force', $options);
$json = array_key_exists('json', $options);
$quiet = array_key_exists('quiet', $options);

$templateKey = 'billing.delinquency_daily_report';
$subject = '[ArtsFolio Billing] Daily delinquency report ' . gmdate('Y-m-d');

$recipients = platform_admin_recipients($pdo);
$report = delinquency_report($pdo);
$body = render_report($root, $report);

$result = [
    'ok' => true,
    'dry_run' => $dryRun,
    'duplicate_suppressed' => false,
    'recipient_count' => count($recipients),
    'queued_count' => 0,
    'critical_count' => $report['critical_count'],
    'warning_count' => $report['warning_count'],
    'past_due_count' => count($report['rows']),
    'template_key' => $templateKey,
];

if ($recipients === []) {
    $result['ok'] = false;
    output_result($result, $json, $quiet, '[FAIL] No platform owner/admin recipients found for billing delinquency report.');
    exit(1);
}

if (!$force && report_already_queued_today($pdo, $templateKey)) {
    $result['duplicate_suppressed'] = true;
    output_result($result, $json, $quiet, '[PASS] Billing delinquency report already queued today; use --force to resend.');
    exit(0);
}

if ($dryRun) {
    output_result($result, $json, $quiet, $body);
    exit(0);
}

$outbox = new EmailOutboxRepository($pdo);
foreach ($recipients as $recipient) {
    $outbox->queue(
        recipientEmail: (string) $recipient['email'],
        subject: $subject,
        bodyText: $body,
        recipientName: $recipient['display_name'] !== null ? (string) $recipient['display_name'] : null,
        tenantId: null,
        userId: (int) $recipient['id'],
        templateKey: $templateKey,
    );
    $result['queued_count'] += 1;
}

output_result($result, $json, $quiet, '[PASS] Queued billing delinquency report for ' . $result['queued_count'] . ' platform admin recipient(s).');
exit(0);

/**
 * @return list<array{id:int,email:string,display_name:?string}>
 */
function platform_admin_recipients(PDO $pdo): array
{
    if (!table_exists($pdo, 'users') || !table_exists($pdo, 'roles') || !table_exists($pdo, 'role_assignments')) {
        return [];
    }

    $statusClause = column_exists($pdo, 'users', 'status') ? "AND u.status = 'active'" : '';

    $stmt = $pdo->query(
        "SELECT DISTINCT u.id, u.email, u.display_name
           FROM users u
           JOIN role_assignments ra ON ra.user_id = u.id
           JOIN roles r ON r.id = ra.role_id
          WHERE ra.tenant_id IS NULL
            AND r.scope = 'platform'
            AND r.slug IN ('owner', 'admin', 'platform_admin')
            AND u.email IS NOT NULL
            AND u.email <> ''
            {$statusClause}
          ORDER BY u.id ASC"
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return array{critical_count:int,warning_count:int,rows:list<array<string,mixed>>,problems:list<array<string,mixed>>}
 */
function delinquency_report(PDO $pdo): array
{
    if (!table_exists($pdo, 'tenant_plan_assignments')) {
        return [
            'critical_count' => 1,
            'warning_count' => 0,
            'rows' => [],
            'problems' => [[
                'severity' => 'CRIT',
                'code' => 'missing_tenant_plan_assignments',
                'message' => 'tenant_plan_assignments table is missing.',
            ]],
        ];
    }

    $columns = table_columns($pdo, 'tenant_plan_assignments');
    $requiredColumns = ['billing_status', 'billing_action_required_at'];
    $missing = array_values(array_diff($requiredColumns, $columns));
    if ($missing !== []) {
        return [
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
        ];
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

    $sql = 'SELECT ' . implode(', ', $select) . '
              FROM tenant_plan_assignments tpa
              JOIN tenants t ON t.id = tpa.tenant_id
              JOIN plans p ON p.id = tpa.plan_id
             WHERE tpa.billing_status IN (\'past_due\', \'unpaid\')
             ORDER BY tpa.billing_action_required_at ASC, tpa.id ASC';

    $sourceRows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $rows = [];
    $critical = 0;
    $warnings = 0;

    foreach ($sourceRows as $row) {
        $actionRequiredAt = $row['billing_action_required_at'] !== null ? (string) $row['billing_action_required_at'] : null;
        $state = BillingDelinquencyPolicy::stateFor((string) ($row['billing_status'] ?? ''), $actionRequiredAt);
        $severity = BillingDelinquencyPolicy::severityForState($state);

        if ($severity === 'CRIT') {
            $critical += 1;
        } elseif ($severity === 'WARN') {
            $warnings += 1;
        }

        $rows[] = [
            'severity' => $severity,
            'state' => $state,
            'state_label' => BillingDelinquencyPolicy::labelForState($state),
            'action' => BillingDelinquencyPolicy::actionForState($state),
            'age_days' => BillingDelinquencyPolicy::ageDays($actionRequiredAt),
            'tenant_slug' => (string) $row['tenant_slug'],
            'tenant_name' => (string) $row['tenant_name'],
            'plan_slug' => (string) $row['plan_slug'],
            'plan_name' => (string) $row['plan_name'],
            'billing_status' => (string) $row['billing_status'],
            'billing_action_required_at' => $row['billing_action_required_at'],
            'latest_invoice_url' => $row['latest_invoice_url'] ?? null,
            'latest_invoice_number' => $row['latest_invoice_number'] ?? null,
            'stripe_customer_id' => $row['stripe_customer_id'] ?? null,
            'stripe_subscription_id' => $row['stripe_subscription_id'] ?? null,
        ];
    }

    return [
        'critical_count' => $critical,
        'warning_count' => $warnings,
        'rows' => $rows,
        'problems' => [],
    ];
}

/**
 * @param array{critical_count:int,warning_count:int,rows:list<array<string,mixed>>,problems:list<array<string,mixed>>} $report
 */
function render_report(string $root, array $report): string
{
    $lines = [];
    if ($report['problems'] !== []) {
        foreach ($report['problems'] as $problem) {
            $lines[] = '[' . $problem['severity'] . '] ' . $problem['code'] . ' | ' . $problem['message'];
        }
    } elseif ($report['rows'] === []) {
        $lines[] = 'No past-due or unpaid tenants were found.';
    } else {
        foreach ($report['rows'] as $row) {
            $lines[] = '[' . $row['severity'] . '] '
                . $row['tenant_slug']
                . ' | ' . $row['state_label']
                . ' | age_days=' . $row['age_days']
                . ' | plan=' . $row['plan_slug']
                . ' | status=' . $row['billing_status']
                . ' | action=' . $row['action']
                . ($row['latest_invoice_url'] ? ' | invoice=' . $row['latest_invoice_url'] : '');
        }
    }

    $renderer = new TemplateRenderer();

    return $renderer->renderFile($root . '/template/email/billing/platform-delinquency-report.txt', [
        'report_date' => gmdate('Y-m-d H:i:s') . ' UTC',
        'critical_count' => (string) $report['critical_count'],
        'warning_count' => (string) $report['warning_count'],
        'past_due_count' => (string) count($report['rows']),
        'report_lines' => implode("\n", $lines),
        'billing_health_url' => 'https://artsfol.io/platform/admin/billing-health',
    ]);
}

function report_already_queued_today(PDO $pdo, string $templateKey): bool
{
    if (!table_exists($pdo, 'email_outbox')) {
        return false;
    }

    $dateColumn = column_exists($pdo, 'email_outbox', 'created_at') ? 'created_at' : 'available_at';
    if (!column_exists($pdo, 'email_outbox', $dateColumn) || !column_exists($pdo, 'email_outbox', 'template_key')) {
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
           FROM email_outbox
          WHERE template_key = :template_key
            AND DATE({$dateColumn}) = UTC_DATE()"
    );
    $stmt->execute(['template_key' => $templateKey]);

    return (int) $stmt->fetchColumn() > 0;
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

function column_exists(PDO $pdo, string $table, string $column): bool
{
    return in_array($column, table_columns($pdo, $table), true);
}

/**
 * @param array<string,mixed> $result
 */
function output_result(array $result, bool $json, bool $quiet, string $message): void
{
    if ($json) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        return;
    }

    if (!$quiet) {
        echo $message . PHP_EOL;
    }
}

// End of file.
