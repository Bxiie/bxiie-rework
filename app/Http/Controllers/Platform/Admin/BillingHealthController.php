<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Http\View\ErrorPage;
use App\Platform\Membership\Roles;
use PDO;
use Throwable;

/**
 * Read-only billing health dashboard for platform administrators.
 *
 * The dashboard is intentionally defensive. It checks table and column
 * existence before every billing query so it remains useful while migrations
 * are being applied across development and production.
 */
final class BillingHealthController
{
    public function __construct(
        private readonly RequirePlatformRole $roles,
        private readonly PDO $pdo,
    ) {
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $issues = $this->collectIssues();
        $summary = $this->summaryCards($issues);
        $issueRows = $this->issueRows($issues);
        $tenantRows = $this->billingTenantRows();
        $webhookRows = $this->webhookRows();
        $schemaRows = $this->schemaRows();

        return Response::html(AdminLayout::render(
            title: 'Billing Health',
            active: 'billing_health',
            body: <<<HTML
<p class="admin-muted">Read-only billing diagnostics for Stripe configuration, tenant subscription state, pending plan changes, and webhook processing.</p>
{$summary}

<div class="admin-card">
    <h2>Actual paying tenants</h2>
    <p class="admin-stat-value"><?= number_format($this->actualPayingTenants()) ?></p>
    <p class="admin-muted">Active, non-complementary tenants on paid plans with confirmed Stripe subscriptions.</p>
</div>

<h2>Attention items</h2>
<table class="admin-table">
    <thead><tr><th>Status</th><th>Check</th><th>Count</th><th>Why it matters</th><th>Action</th></tr></thead>
    <tbody>{$issueRows}</tbody>
</table>
<h2>Tenants needing billing attention</h2>
<table class="admin-table">
    <thead><tr><th>Tenant</th><th>Plan</th><th>Status</th><th>Recurring date</th><th>Pending change</th><th>Stripe</th><th>Latest issue</th></tr></thead>
    <tbody>{$tenantRows}</tbody>
</table>
<h2>Recent Stripe webhooks</h2>
<table class="admin-table">
    <thead><tr><th>Received</th><th>Event</th><th>Status</th><th>Attempts</th><th>Object</th><th>Error</th></tr></thead>
    <tbody>{$webhookRows}</tbody>
</table>
<h2>Billing schema readiness</h2>
<table class="admin-table">
    <thead><tr><th>Object</th><th>Status</th><th>Details</th></tr></thead>
    <tbody>{$schemaRows}</tbody>
</table>
HTML,
        ));
    }

    /**
     * @return list<array{key:string,label:string,severity:string,count:int,why:string,action:string}>
     */
    private function collectIssues(): array
    {
        $issues = [];

        $issues[] = [
            'key' => 'paid_plans_missing_price_ids',
            'label' => 'Paid plans missing Stripe Price IDs',
            'severity' => $this->countPaidPlansMissingPriceIds() > 0 ? 'CRIT' : 'OK',
            'count' => $this->countPaidPlansMissingPriceIds(),
            'why' => 'Paid checkout and subscription changes require durable Stripe monthly Price IDs.',
            'action' => 'Open Platform Admin → Plans & Billing and add price_... IDs to every active paid plan.',
        ];

        $issues[] = [
            'key' => 'free_plans_with_price_ids',
            'label' => 'Free plans with Stripe Price IDs',
            'severity' => $this->countFreePlansWithPriceIds() > 0 ? 'WARN' : 'OK',
            'count' => $this->countFreePlansWithPriceIds(),
            'why' => 'Free plans should not be connected to Stripe checkout prices.',
            'action' => 'Clear Stripe product/price fields on active free plans.',
        ];

        $issues[] = [
            'key' => 'payment_pending',
            'label' => 'Tenants pending paid checkout completion',
            'severity' => $this->countAssignmentsByBillingStatus('payment_pending') > 0 ? 'WARN' : 'OK',
            'count' => $this->countAssignmentsByBillingStatus('payment_pending'),
            'why' => 'These tenants selected a paid plan but Stripe checkout has not completed.',
            'action' => 'Review tenant billing detail and Stripe checkout sessions.',
        ];

        $issues[] = [
            'key' => 'past_due',
            'label' => 'Past-due subscriptions',
            'severity' => $this->countAssignmentsByBillingStatus('past_due') > 0 ? 'CRIT' : 'OK',
            'count' => $this->countAssignmentsByBillingStatus('past_due'),
            'why' => 'Stripe reported failed or unpaid subscription billing.',
            'action' => 'Ask tenant owner to use Update payment method in Tenant Admin → Billing.',
        ];

        $issues[] = [
            'key' => 'overdue_pending_changes',
            'label' => 'Overdue scheduled downgrades/cancellations',
            'severity' => $this->countOverduePendingChanges() > 0 ? 'CRIT' : 'OK',
            'count' => $this->countOverduePendingChanges(),
            'why' => 'Scheduled end-of-period plan changes should be applied by the billing scheduler.',
            'action' => 'Run php scripts/billing/apply_pending_subscription_changes.php --dry-run and check artsfolio-billing-scheduler.timer.',
        ];

        $issues[] = [
            'key' => 'missing_subscription_item_ids',
            'label' => 'Subscriptions missing item IDs',
            'severity' => $this->countMissingSubscriptionItemIds() > 0 ? 'WARN' : 'OK',
            'count' => $this->countMissingSubscriptionItemIds(),
            'why' => 'Paid-to-paid plan changes need the Stripe subscription item ID.',
            'action' => 'Confirm customer.subscription.created/updated webhooks are enabled and replay if needed.',
        ];

        $issues[] = [
            'key' => 'portal_errors',
            'label' => 'Stripe Billing Portal errors',
            'severity' => $this->countPortalErrors() > 0 ? 'WARN' : 'OK',
            'count' => $this->countPortalErrors(),
            'why' => 'Tenant owners may be unable to update cards or recover failed payments.',
            'action' => 'Check Stripe secret key, customer IDs, and optional stripe_billing_portal_configuration_id.',
        ];

        $issues[] = [
            'key' => 'failed_webhooks',
            'label' => 'Failed Stripe webhook events',
            'severity' => $this->countWebhookStatus('failed') > 0 ? 'CRIT' : 'OK',
            'count' => $this->countWebhookStatus('failed'),
            'why' => 'Failed Stripe events may leave local billing state out of sync.',
            'action' => 'Review stripe_webhook_events.last_error and Stripe webhook retries.',
        ];

        $issues[] = [
            'key' => 'stuck_processing_webhooks',
            'label' => 'Stuck processing webhook events',
            'severity' => $this->countStuckProcessingWebhooks() > 0 ? 'WARN' : 'OK',
            'count' => $this->countStuckProcessingWebhooks(),
            'why' => 'Events stuck in processing may indicate a crash during webhook handling.',
            'action' => 'Inspect recent application logs and stripe_webhook_events rows older than 15 minutes.',
        ];

        return $issues;
    }

    /** @param list<array{severity:string,count:int}> $issues */
    private function summaryCards(array $issues): string
    {
        $critical = 0;
        $warning = 0;
        $ok = 0;

        foreach ($issues as $issue) {
            if ($issue['severity'] === 'CRIT') {
                $critical += 1;
            } elseif ($issue['severity'] === 'WARN') {
                $warning += 1;
            } else {
                $ok += 1;
            }
        }

        return '<div class="admin-dashboard-cards">'
            . $this->summaryCard('Critical', $critical, 'Immediate billing risk')
            . $this->summaryCard('Warnings', $warning, 'Needs review')
            . $this->summaryCard('Healthy checks', $ok, 'No action')
            . $this->summaryCard('Webhook failures', $this->countWebhookStatus('failed'), 'Stripe event errors')
            . '</div>';
    }

    private function summaryCard(string $label, int $count, string $note): string
    {
        return '<article class="admin-stat-card"><strong>' . AdminLayout::escape((string) $count) . '</strong><span>' . AdminLayout::escape($label) . '</span><small>' . AdminLayout::escape($note) . '</small></article>';
    }

    /** @param list<array{label:string,severity:string,count:int,why:string,action:string}> $issues */
    private function issueRows(array $issues): string
    {
        $rows = '';
        foreach ($issues as $issue) {
            $rows .= '<tr>'
                . '<td>' . $this->badge($issue['severity']) . '</td>'
                . '<td><strong>' . AdminLayout::escape($issue['label']) . '</strong></td>'
                . '<td>' . AdminLayout::escape((string) $issue['count']) . '</td>'
                . '<td>' . AdminLayout::escape($issue['why']) . '</td>'
                . '<td>' . AdminLayout::escape($issue['action']) . '</td>'
                . '</tr>';
        }

        return $rows !== '' ? $rows : '<tr><td colspan="5">No billing checks available.</td></tr>';
    }

    private function billingTenantRows(): string
    {
        if (!$this->tableExists('tenant_plan_assignments')) {
            return '<tr><td colspan="7">tenant_plan_assignments is not available.</td></tr>';
        }

        $columns = $this->columns('tenant_plan_assignments');
        $select = [
            't.id AS tenant_id',
            't.slug AS tenant_slug',
            't.name AS tenant_name',
            'p.slug AS plan_slug',
            'p.name AS plan_name',
            'tpa.status AS assignment_status',
        ];

        foreach ([
            'billing_status',
            'current_period_ends_at',
            'pending_change_type',
            'pending_plan_slug',
            'pending_effective_at',
            'stripe_customer_id',
            'stripe_subscription_id',
            'stripe_subscription_item_id',
            'latest_stripe_error',
            'billing_action_required_at',
            'last_payment_failed_at',
        ] as $column) {
            $select[] = in_array($column, $columns, true) ? 'tpa.' . $column : 'NULL AS ' . $column;
        }

        $where = [];
        if (in_array('billing_status', $columns, true)) {
            $where[] = "tpa.billing_status IN ('payment_pending', 'past_due', 'unpaid', 'canceled')";
        }
        if (in_array('pending_change_type', $columns, true) && in_array('pending_effective_at', $columns, true)) {
            $appliedClause = in_array('pending_change_applied_at', $columns, true) ? ' AND tpa.pending_change_applied_at IS NULL' : '';
            $where[] = "(tpa.pending_change_type IN ('downgrade', 'cancel') AND tpa.pending_effective_at <= UTC_TIMESTAMP()" . $appliedClause . ')';
        }
        if (in_array('latest_stripe_error', $columns, true)) {
            $where[] = "(tpa.latest_stripe_error IS NOT NULL AND tpa.latest_stripe_error <> '')";
        }

        if ($where === []) {
            return '<tr><td colspan="7">Billing diagnostic columns are not available yet. Apply migrations 0049-0053.</td></tr>';
        }

        // Use a schema-tolerant ORDER BY because older billing schemas may not have updated_at.
        $orderBy = 'tpa.id DESC';
        if (in_array('updated_at', $columns, true)) {
            $orderBy = 'tpa.updated_at DESC, tpa.id DESC';
        } elseif (in_array('created_at', $columns, true)) {
            $orderBy = 'tpa.created_at DESC, tpa.id DESC';
        }

        $sql = 'SELECT ' . implode(', ', $select) . '
                  FROM tenant_plan_assignments tpa
                  JOIN tenants t ON t.id = tpa.tenant_id
                  JOIN plans p ON p.id = tpa.plan_id
                 WHERE ' . implode(' OR ', $where) . '
                 ORDER BY ' . $orderBy . '
                 LIMIT 50';

        try {
            $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return '<tr><td colspan="7">Billing tenant query failed: ' . AdminLayout::escape($e->getMessage()) . '</td></tr>';
        }

        $html = '';
        foreach ($rows as $row) {
            $tenantUrl = '/platform/admin/tenants/' . (int) $row['tenant_id'];
            $tenant = '<a href="' . AdminLayout::escape($tenantUrl) . '">' . AdminLayout::escape((string) $row['tenant_slug']) . '</a><br><small>' . AdminLayout::escape((string) $row['tenant_name']) . '</small>';
            $plan = AdminLayout::escape((string) ($row['plan_name'] ?? $row['plan_slug'] ?? 'Unknown'));
            $status = AdminLayout::escape((string) ($row['billing_status'] ?? $row['assignment_status'] ?? ''));
            $recurs = AdminLayout::escape((string) ($row['current_period_ends_at'] ?? ''));
            $pending = trim((string) ($row['pending_change_type'] ?? '')) !== ''
                ? AdminLayout::escape((string) $row['pending_change_type']) . ' to ' . AdminLayout::escape((string) ($row['pending_plan_slug'] ?? 'selected plan')) . '<br><small>' . AdminLayout::escape((string) ($row['pending_effective_at'] ?? '')) . '</small>'
                : 'None';
            $stripe = 'Customer: ' . AdminLayout::escape((string) ($row['stripe_customer_id'] ?? '')) . '<br>Sub: ' . AdminLayout::escape((string) ($row['stripe_subscription_id'] ?? '')) . '<br>Item: ' . AdminLayout::escape((string) ($row['stripe_subscription_item_id'] ?? ''));
            $issue = AdminLayout::escape((string) ($row['latest_stripe_error'] ?? ''));
            if ($issue === '') {
                $issue = AdminLayout::escape((string) ($row['billing_action_required_at'] ?? $row['last_payment_failed_at'] ?? ''));
            }

            $html .= '<tr><td>' . $tenant . '</td><td>' . $plan . '</td><td>' . $status . '</td><td>' . $recurs . '</td><td>' . $pending . '</td><td><small>' . $stripe . '</small></td><td>' . $issue . '</td></tr>';
        }

        return $html !== '' ? $html : '<tr><td colspan="7">No tenants currently need billing attention.</td></tr>';
    }

    private function webhookRows(): string
    {
        if (!$this->tableExists('stripe_webhook_events')) {
            return '<tr><td colspan="6">stripe_webhook_events is not available yet. Apply migration 0053.</td></tr>';
        }

        try {
            $rows = $this->pdo->query(
                'SELECT event_type, status, attempt_count, stripe_object_id, received_at, last_error
                   FROM stripe_webhook_events
                  ORDER BY id DESC
                  LIMIT 25'
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return '<tr><td colspan="6">Webhook query failed: ' . AdminLayout::escape($e->getMessage()) . '</td></tr>';
        }

        $html = '';
        foreach ($rows as $row) {
            $html .= '<tr>'
                . '<td>' . AdminLayout::escape((string) $row['received_at']) . '</td>'
                . '<td>' . AdminLayout::escape((string) $row['event_type']) . '</td>'
                . '<td>' . $this->badge((string) $row['status']) . '</td>'
                . '<td>' . AdminLayout::escape((string) $row['attempt_count']) . '</td>'
                . '<td><code>' . AdminLayout::escape((string) ($row['stripe_object_id'] ?? '')) . '</code></td>'
                . '<td>' . AdminLayout::escape(mb_substr((string) ($row['last_error'] ?? ''), 0, 240)) . '</td>'
                . '</tr>';
        }

        return $html !== '' ? $html : '<tr><td colspan="6">No Stripe webhook events have been recorded yet.</td></tr>';
    }

    private function schemaRows(): string
    {
        $checks = [
            ['plans.stripe_monthly_price_id', $this->columnExists('plans', 'stripe_monthly_price_id'), 'Required for stable Stripe plan prices.'],
            ['tenant_plan_assignments.billing_status', $this->columnExists('tenant_plan_assignments', 'billing_status'), 'Required for payment_pending/past_due diagnostics.'],
            ['tenant_plan_assignments.current_period_ends_at', $this->columnExists('tenant_plan_assignments', 'current_period_ends_at'), 'Required for recurring billing date display.'],
            ['tenant_plan_assignments.stripe_subscription_item_id', $this->columnExists('tenant_plan_assignments', 'stripe_subscription_item_id'), 'Required for paid-to-paid subscription item updates.'],
            ['tenant_plan_assignments.latest_stripe_error', $this->columnExists('tenant_plan_assignments', 'latest_stripe_error'), 'Required for Billing Portal error diagnostics.'],
            ['stripe_webhook_events', $this->tableExists('stripe_webhook_events'), 'Required for webhook idempotency and black-box recorder diagnostics.'],
        ];

        $rows = '';
        foreach ($checks as [$name, $ok, $details]) {
            $rows .= '<tr><td><code>' . AdminLayout::escape($name) . '</code></td><td>' . $this->badge($ok ? 'OK' : 'MISSING') . '</td><td>' . AdminLayout::escape($details) . '</td></tr>';
        }

        return $rows;
    }

    private function countPaidPlansMissingPriceIds(): int
    {
        if (!$this->columnExists('plans', 'stripe_monthly_price_id')) {
            return 0;
        }

        return $this->scalarInt("SELECT COUNT(*) FROM plans WHERE is_active = 1 AND monthly_price_cents > 0 AND (stripe_monthly_price_id IS NULL OR stripe_monthly_price_id = '')");
    }

    private function countFreePlansWithPriceIds(): int
    {
        if (!$this->columnExists('plans', 'stripe_monthly_price_id')) {
            return 0;
        }

        return $this->scalarInt("SELECT COUNT(*) FROM plans WHERE is_active = 1 AND monthly_price_cents = 0 AND (stripe_monthly_price_id IS NOT NULL AND stripe_monthly_price_id <> '')");
    }

    private function countAssignmentsByBillingStatus(string $status): int
    {
        if (!$this->columnExists('tenant_plan_assignments', 'billing_status')) {
            return 0;
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tenant_plan_assignments WHERE billing_status = :status');
        $stmt->execute(['status' => $status]);

        return (int) $stmt->fetchColumn();
    }

    private function countOverduePendingChanges(): int
    {
        $columns = $this->columns('tenant_plan_assignments');
        if (!in_array('pending_change_type', $columns, true) || !in_array('pending_effective_at', $columns, true)) {
            return 0;
        }

        $appliedClause = in_array('pending_change_applied_at', $columns, true) ? ' AND pending_change_applied_at IS NULL' : '';

        return $this->scalarInt("SELECT COUNT(*) FROM tenant_plan_assignments WHERE pending_change_type IN ('downgrade', 'cancel') AND pending_effective_at <= UTC_TIMESTAMP()" . $appliedClause);
    }

    private function countMissingSubscriptionItemIds(): int
    {
        $columns = $this->columns('tenant_plan_assignments');
        if (!in_array('stripe_subscription_id', $columns, true) || !in_array('stripe_subscription_item_id', $columns, true)) {
            return 0;
        }

        return $this->scalarInt("SELECT COUNT(*) FROM tenant_plan_assignments WHERE stripe_subscription_id IS NOT NULL AND stripe_subscription_id <> '' AND (stripe_subscription_item_id IS NULL OR stripe_subscription_item_id = '')");
    }

    private function countPortalErrors(): int
    {
        if (!$this->columnExists('tenant_plan_assignments', 'latest_stripe_error')) {
            return 0;
        }

        return $this->scalarInt("SELECT COUNT(*) FROM tenant_plan_assignments WHERE latest_stripe_error IS NOT NULL AND latest_stripe_error <> ''");
    }

    private function countWebhookStatus(string $status): int
    {
        if (!$this->tableExists('stripe_webhook_events')) {
            return 0;
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM stripe_webhook_events WHERE status = :status');
        $stmt->execute(['status' => $status]);

        return (int) $stmt->fetchColumn();
    }

    private function countStuckProcessingWebhooks(): int
    {
        if (!$this->tableExists('stripe_webhook_events')) {
            return 0;
        }

        return $this->scalarInt("SELECT COUNT(*) FROM stripe_webhook_events WHERE status = 'processing' AND received_at < (UTC_TIMESTAMP() - INTERVAL 15 MINUTE)");
    }

    private function scalarInt(string $sql): int
    {
        try {
            return (int) $this->pdo->query($sql)->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    /** @return list<string> */
    private function columns(string $table): array
    {
        if (!$this->tableExists($table)) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT column_name
               FROM information_schema.columns
              WHERE table_schema = DATABASE()
                AND table_name = :table'
        );
        $stmt->execute(['table' => $table]);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
               FROM information_schema.tables
              WHERE table_schema = DATABASE()
                AND table_name = :table'
        );
        $stmt->execute(['table' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        return in_array($column, $this->columns($table), true);
    }

    private function badge(string $status): string
    {
        $label = strtoupper($status);
        $class = match ($label) {
            'OK', 'PROCESSED' => 'admin-notice-success',
            'WARN', 'PROCESSING', 'MISSING' => 'admin-notice-warning',
            default => 'admin-notice-error',
        };

        return '<span class="admin-notice ' . $class . '">' . AdminLayout::escape($label) . '</span>';
    }
    private function actualPayingTenants(): int
    {
        if (!$this->tableExists('tenant_plan_assignments') || !$this->tableExists('plans') || !$this->tableExists('tenants')) {
            return 0;
        }

        if (!$this->columnExists('tenant_plan_assignments', 'stripe_subscription_id')) {
            return 0;
        }

        $tenantStatusClause = $this->columnExists('tenants', 'status') ? 't.status = "active"' : '1 = 1';
        $complementaryClause = $this->columnExists('tenants', 'complementary') ? 'COALESCE(t.complementary, 0) = 0' : '1 = 1';
        $billingStatusClause = $this->columnExists('tenant_plan_assignments', 'billing_status')
            ? 'COALESCE(tpa.billing_status, "active") IN ("active", "past_due", "unpaid")'
            : '1 = 1';

        return $this->scalarInt(
            'SELECT COUNT(DISTINCT tpa.tenant_id)
               FROM tenant_plan_assignments tpa
               JOIN plans p ON p.id = tpa.plan_id
               JOIN tenants t ON t.id = tpa.tenant_id
              WHERE ' . $tenantStatusClause . '
                AND ' . $complementaryClause . '
                AND p.monthly_price_cents > 0
                AND (tpa.stripe_subscription_id IS NOT NULL AND tpa.stripe_subscription_id <> "")
                AND ' . $billingStatusClause
        );
    }


}

// End of file.
