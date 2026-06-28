<?php

declare(strict_types=1);

/**
 * Read-only tenant billing state inspector.
 *
 * Command:
 *   php scripts/billing/inspect_tenant_billing_state.php --tenant=bxiie
 *
 * Supported CLI options: --tenant:, --json
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

$options = getopt('', ['tenant:', 'json']);
$json = array_key_exists('json', $options);
$tenantFilter = isset($options['tenant']) ? trim((string) $options['tenant']) : '';

if ($tenantFilter === '') {
    fwrite(STDERR, "[FAIL] Missing --tenant=slug-or-id\n");
    exit(2);
}

$tenant = find_tenant($pdo, $tenantFilter);
if ($tenant === null) {
    fwrite(STDERR, "[FAIL] Tenant not found: {$tenantFilter}\n");
    exit(1);
}

$result = [
    'tenant' => $tenant,
    'plans' => table_exists($pdo, 'plans') ? plans($pdo) : [],
    'tenant_plan_assignments' => table_exists($pdo, 'tenant_plan_assignments') ? tenant_assignments($pdo, (int) $tenant['id']) : [],
    'stripe_webhook_events' => table_exists($pdo, 'stripe_webhook_events') ? recent_webhooks($pdo) : [],
    'email_outbox_billing' => table_exists($pdo, 'email_outbox') ? recent_billing_outbox($pdo, (int) $tenant['id']) : [],
];

if ($json) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

echo "Tenant\n";
print_rows([$tenant]);

echo "\nPlans\n";
print_rows($result['plans']);

echo "\nTenant plan assignments\n";
print_rows($result['tenant_plan_assignments']);

echo "\nRecent Stripe webhook events\n";
print_rows($result['stripe_webhook_events']);

echo "\nRecent billing email outbox rows\n";
print_rows($result['email_outbox_billing']);

exit(0);

function find_tenant(PDO $pdo, string $tenantFilter): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE slug = :tenant_filter OR CAST(id AS CHAR) = :tenant_filter LIMIT 1");
    $stmt->execute(['tenant_filter' => $tenantFilter]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row !== false ? $row : null;
}

/** @return list<array<string,mixed>> */
function plans(PDO $pdo): array
{
    $columns = table_columns($pdo, 'plans');
    $wanted = [
        'id',
        'slug',
        'name',
        'monthly_price_cents',
        'is_active',
        'stripe_product_id',
        'stripe_monthly_price_id',
        'stripe_price_lookup_key',
    ];

    $select = [];
    foreach ($wanted as $column) {
        $select[] = in_array($column, $columns, true) ? $column : 'NULL AS ' . $column;
    }

    return $pdo->query('SELECT ' . implode(', ', $select) . ' FROM plans ORDER BY monthly_price_cents ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string,mixed>> */
function tenant_assignments(PDO $pdo, int $tenantId): array
{
    $columns = table_columns($pdo, 'tenant_plan_assignments');
    $wanted = [
        'id',
        'tenant_id',
        'plan_id',
        'billing_status',
        'stripe_subscription_status',
        'pending_change_type',
        'pending_plan_id',
        'pending_effective_at',
        'pending_proration_cents',
        'stripe_customer_id',
        'stripe_subscription_id',
        'stripe_subscription_item_id',
        'stripe_checkout_session_id',
        'stripe_pending_update_id',
        'current_period_starts_at',
        'current_period_ends_at',
        'billing_action_required_at',
        'last_payment_failed_at',
        'latest_invoice_url',
        'latest_invoice_number',
        'latest_stripe_error',
        'billing_note',
        'created_at',
        'updated_at',
    ];

    $select = [];
    foreach ($wanted as $column) {
        $select[] = in_array($column, $columns, true) ? 'tpa.' . $column : 'NULL AS ' . $column;
    }

    $sql = 'SELECT ' . implode(', ', $select) . ',
                   current_plan.slug AS current_plan_slug,
                   current_plan.name AS current_plan_name,
                   current_plan.monthly_price_cents AS current_plan_monthly_price_cents,
                   pending_plan.slug AS pending_plan_slug,
                   pending_plan.name AS pending_plan_name,
                   pending_plan.monthly_price_cents AS pending_plan_monthly_price_cents
              FROM tenant_plan_assignments tpa
              LEFT JOIN plans current_plan ON current_plan.id = tpa.plan_id
              LEFT JOIN plans pending_plan ON pending_plan.id = tpa.pending_plan_id
             WHERE tpa.tenant_id = :tenant_id
             ORDER BY tpa.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['tenant_id' => $tenantId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string,mixed>> */
function recent_webhooks(PDO $pdo): array
{
    $columns = table_columns($pdo, 'stripe_webhook_events');
    $wanted = ['id', 'event_id', 'event_type', 'stripe_object_id', 'status', 'attempt_count', 'response_code', 'last_error', 'received_at', 'processed_at', 'created_at', 'updated_at'];

    $select = [];
    foreach ($wanted as $column) {
        $select[] = in_array($column, $columns, true) ? $column : 'NULL AS ' . $column;
    }

    return $pdo->query('SELECT ' . implode(', ', $select) . ' FROM stripe_webhook_events ORDER BY id DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string,mixed>> */
function recent_billing_outbox(PDO $pdo, int $tenantId): array
{
    $columns = table_columns($pdo, 'email_outbox');
    $wanted = ['id', 'tenant_id', 'user_id', 'recipient_email', 'subject', 'template_key', 'status', 'available_at', 'sent_at', 'last_error', 'created_at', 'updated_at'];

    $select = [];
    foreach ($wanted as $column) {
        $select[] = in_array($column, $columns, true) ? $column : 'NULL AS ' . $column;
    }

    $where = ['template_key LIKE "billing.%"'];
    $params = [];
    if (in_array('tenant_id', $columns, true)) {
        $where[] = '(tenant_id = :tenant_id OR tenant_id IS NULL)';
        $params['tenant_id'] = $tenantId;
    }

    $sql = 'SELECT ' . implode(', ', $select) . ' FROM email_outbox WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 20';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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

/** @param list<array<string,mixed>> $rows */
function print_rows(array $rows): void
{
    if ($rows === []) {
        echo "(none)\n";
        return;
    }

    foreach ($rows as $index => $row) {
        echo '-- row ' . ($index + 1) . "--\n";
        foreach ($row as $key => $value) {
            if ($value === null) {
                $value = 'NULL';
            }
            echo $key . ': ' . (string) $value . "\n";
        }
    }
}

// End of file.
