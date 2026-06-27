#!/usr/bin/python3
# Apply ArtsFolio Platform Admin billing health diagnostics.
#
# This pass adds a read-only billing health dashboard for platform admins. It
# intentionally adds no schema. The controller checks table/column existence
# before querying billing fields so it remains useful during partially applied
# billing migrations.
#
# The patch is idempotent, backs up changed files, updates docs and
# PROJECT_STATE.md, and does not modify fix.txt.

from __future__ import annotations

import datetime as dt
import json
import re
import shutil
import subprocess
import sys
from pathlib import Path
from typing import Any, NoReturn


PROJECT_ROOT = Path("/Users/bxiie/Dropbox/tcdev/artsfolio")
BACKUP_ROOT = PROJECT_ROOT / ".update-backups"
EXPECTED_ROUTE_ADDITION = ("platform", "GET", "/platform/admin/billing-health")

FILES_TO_BACK_UP = [
    "app/Http/Controllers/Platform/Admin/BillingHealthController.php",
    "app/Http/Routes/platform.php",
    "app/Http/View/AdminLayout.php",
    "scripts/test/platform_billing_health_static.php",
    "scripts/test/fixtures/route_inventory.json",
    "docs/admin/billing_health.md",
    "docs/dev/subscription_billing.md",
    "PROJECT_STATE.md",
]


def fail(message: str) -> NoReturn:
    print(f"[FAIL] {message}", file=sys.stderr)
    raise SystemExit(1)


def project_path(rel: str) -> Path:
    return PROJECT_ROOT / rel


def read(rel: str) -> str:
    p = project_path(rel)
    try:
        return p.read_text(encoding="utf-8")
    except FileNotFoundError:
        fail(f"Missing required file: {rel}")


def write(rel: str, content: str) -> None:
    p = project_path(rel)
    p.parent.mkdir(parents=True, exist_ok=True)
    p.write_text(content, encoding="utf-8")
    print(f"[PASS] Updated {rel}")


def run(command: list[str], label: str) -> None:
    print(f"[RUN] {label}")
    result = subprocess.run(command, cwd=PROJECT_ROOT, check=False)
    if result.returncode != 0:
        fail(f"{label} failed with exit code {result.returncode}")
    print(f"[PASS] {label}")


def backup() -> None:
    timestamp = dt.datetime.now().strftime("%Y%m%d%H%M%S")
    backup_dir = BACKUP_ROOT / f"billing-health-dashboard-{timestamp}"
    backup_dir.mkdir(parents=True, exist_ok=False)

    for rel in FILES_TO_BACK_UP:
        p = project_path(rel)
        if not p.exists():
            continue
        destination = backup_dir / rel
        destination.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(p, destination)

    print(f"[PASS] Backup created at {backup_dir}")


def ensure_prerequisites() -> None:
    if not PROJECT_ROOT.is_dir():
        fail(f"Project root does not exist: {PROJECT_ROOT}")

    required = [
        "app/Http/Routes/platform.php",
        "app/Http/View/AdminLayout.php",
        "database/migrations/0049_subscription_billing_workflow.sql",
        "database/migrations/0051_stable_stripe_plan_price_ids.sql",
    ]

    for rel in required:
        if not project_path(rel).is_file():
            fail(f"Missing prerequisite file: {rel}")


def write_controller() -> None:
    content = '''<?php

declare(strict_types=1);

namespace App\\Http\\Controllers\\Platform\\Admin;

use App\\Http\\Middleware\\RequirePlatformRole;
use App\\Http\\Request;
use App\\Http\\Response;
use App\\Http\\View\\AdminLayout;
use App\\Http\\View\\ErrorPage;
use App\\Platform\\Membership\\Roles;
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

        $sql = 'SELECT ' . implode(', ', $select) . '
                  FROM tenant_plan_assignments tpa
                  JOIN tenants t ON t.id = tpa.tenant_id
                  JOIN plans p ON p.id = tpa.plan_id
                 WHERE ' . implode(' OR ', $where) . '
                 ORDER BY tpa.updated_at DESC, tpa.id DESC
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
}

// End of file.
'''
    write("app/Http/Controllers/Platform/Admin/BillingHealthController.php", content)


def patch_platform_routes() -> None:
    rel = "app/Http/Routes/platform.php"
    content = read(rel)

    use_line = "use App\\Http\\Controllers\\Platform\\Admin\\BillingHealthController as PlatformAdminBillingHealthController;\n"
    if use_line not in content:
        anchor = "use App\\Http\\Controllers\\Platform\\Admin\\PricingController as PlatformAdminPricingController;\n"
        if anchor not in content:
            fail("Could not locate PricingController use statement in platform routes")
        content = content.replace(anchor, anchor + use_line, 1)
        print("[PASS] Added BillingHealthController use statement")
    else:
        print("[PASS] BillingHealthController use statement already present")

    route = "    $router->get('/platform/admin/billing-health', fn (Request $request): Response => (new PlatformAdminBillingHealthController(new RequirePlatformRole(new MembershipRepository($pdo)), $pdo))->index($request, $currentUser));\n"
    if route not in content:
        anchor = "    $router->get('/platform/admin/pricing', fn (Request $request): Response => (new PlatformAdminPricingController(new RequirePlatformRole(new MembershipRepository($pdo)), $pdo, new PlatformSettingsRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo)))->index($request, $currentUser));\n"
        if anchor not in content:
            fail("Could not locate /platform/admin/pricing route in platform routes")
        content = content.replace(anchor, anchor + route, 1)
        print("[PASS] Added /platform/admin/billing-health route")
    else:
        print("[PASS] /platform/admin/billing-health route already present")

    write(rel, content)


def patch_admin_nav() -> None:
    rel = "app/Http/View/AdminLayout.php"
    content = read(rel)

    nav_line = "            'billing_health' => ['/platform/admin/billing-health', 'Billing Health'],\n"
    if nav_line not in content:
        anchor = "            'pricing' => ['/platform/admin/pricing', 'Plans & Billing'],\n"
        if anchor not in content:
            fail("Could not locate pricing nav item in AdminLayout")
        content = content.replace(anchor, anchor + nav_line, 1)
        print("[PASS] Added Billing Health platform admin nav item")
    else:
        print("[PASS] Billing Health nav item already present")

    write(rel, content)


def update_route_fixture() -> None:
    fixture = project_path("scripts/test/fixtures/route_inventory.json")
    inventory_script = project_path("scripts/test/route_inventory.php")

    if not fixture.is_file() or not inventory_script.is_file():
        print("[INFO] Route inventory fixture or generator is missing; skipping fixture synchronization.")
        return

    result = subprocess.run(
        ["php", str(inventory_script)],
        cwd=PROJECT_ROOT,
        check=False,
        capture_output=True,
        text=True,
    )
    if result.returncode != 0:
        fail("Route inventory generator failed: " + (result.stderr.strip() or result.stdout.strip()))

    try:
        generated = json.loads(result.stdout)
        existing = json.loads(fixture.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        fail(f"Route inventory JSON error: {exc}")

    def route_key(row: dict[str, Any]) -> tuple[str, str, str]:
        return (str(row["scope"]), str(row["method"]), str(row["path"]))

    existing_keys = {route_key(row) for row in existing}
    generated_keys = {route_key(row) for row in generated}
    additions = generated_keys - existing_keys
    removals = existing_keys - generated_keys

    if not additions and not removals:
        print("[PASS] Route inventory fixture is already current.")
        return

    if removals:
        fail("Refusing to update route fixture because routes were removed: " + ", ".join(map(str, sorted(removals))))

    if additions != {EXPECTED_ROUTE_ADDITION}:
        fail("Refusing to update route fixture because additions were not exactly GET /platform/admin/billing-health: " + ", ".join(map(str, sorted(additions))))

    fixture.write_text(json.dumps(generated, indent=4, ensure_ascii=False) + "\n", encoding="utf-8")
    print("[PASS] Updated scripts/test/fixtures/route_inventory.json for GET /platform/admin/billing-health")


def write_static_test() -> None:
    content = '''<?php

declare(strict_types=1);

/**
 * Static coverage for Platform Admin billing health diagnostics.
 */

$root = dirname(__DIR__, 2);

$files = [
    'controller' => $root . '/app/Http/Controllers/Platform/Admin/BillingHealthController.php',
    'routes' => $root . '/app/Http/Routes/platform.php',
    'layout' => $root . '/app/Http/View/AdminLayout.php',
    'admin_docs' => $root . '/docs/admin/billing_health.md',
    'state' => $root . '/PROJECT_STATE.md',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$label}: {$path}\\n");
        exit(1);
    }
}

$controller = file_get_contents($files['controller']);
$routes = file_get_contents($files['routes']);
$layout = file_get_contents($files['layout']);
$adminDocs = file_get_contents($files['admin_docs']);
$state = file_get_contents($files['state']);

$required = [
    [$controller, 'final class BillingHealthController', 'controller class must exist'],
    [$controller, 'Paid plans missing Stripe Price IDs', 'controller must check paid plan price IDs'],
    [$controller, 'Past-due subscriptions', 'controller must check past-due tenants'],
    [$controller, 'Overdue scheduled downgrades/cancellations', 'controller must check overdue scheduled changes'],
    [$controller, 'Failed Stripe webhook events', 'controller must check failed webhooks'],
    [$controller, 'stripe_webhook_events', 'controller must inspect webhook table when present'],
    [$controller, 'information_schema.columns', 'controller must be schema tolerant'],
    [$controller, 'latest_stripe_error', 'controller must surface portal/Stripe errors'],
    [$routes, 'PlatformAdminBillingHealthController', 'platform routes must import billing health controller'],
    [$routes, "/platform/admin/billing-health", 'platform routes must register billing health page'],
    [$layout, "'billing_health' => ['/platform/admin/billing-health', 'Billing Health']", 'admin nav must include Billing Health'],
    [$adminDocs, 'Billing Health', 'admin docs must describe billing health page'],
    [$state, 'Platform Admin billing health dashboard', 'PROJECT_STATE must record billing health dashboard'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}\\n");
        exit(1);
    }
}

echo "Platform billing health static checks passed.\\n";

// End of file.
'''
    write("scripts/test/platform_billing_health_static.php", content)


def patch_docs() -> None:
    admin_doc = '''# Billing Health

Platform Admin → Billing Health is a read-only diagnostic dashboard for subscription billing.

It highlights:

- active paid plans missing `stripe_monthly_price_id`
- free plans that accidentally have Stripe Price IDs
- tenants stuck in `payment_pending`
- tenants marked `past_due`
- overdue scheduled downgrades or cancellations
- subscriptions missing `stripe_subscription_item_id`
- Stripe Billing Portal errors
- failed or stuck Stripe webhook events
- billing migration readiness

Use this page after billing deployments, Stripe webhook changes, failed-payment reports, and production migrations.

Common follow-up commands:

```bash
cd /var/www/artsfolio
php scripts/billing/apply_pending_subscription_changes.php --dry-run
sudo systemctl status artsfolio-billing-scheduler.timer
sudo journalctl -u artsfolio-billing-scheduler.service -n 100 --no-pager
```

Webhook diagnostics live in `stripe_webhook_events` after migration 0053.

<!-- End of file. -->
'''
    write("docs/admin/billing_health.md", admin_doc)

    dev_rel = "docs/dev/subscription_billing.md"
    dev_doc = read(dev_rel) if project_path(dev_rel).is_file() else "# Subscription Billing\n\n"
    if "## Platform Admin billing health dashboard" not in dev_doc:
        dev_doc = dev_doc.rstrip() + '''

## Platform Admin billing health dashboard

`app/Http/Controllers/Platform/Admin/BillingHealthController.php` powers Platform Admin → Billing Health at `/platform/admin/billing-health`.

The controller is read-only and schema-tolerant. It checks `information_schema` before querying optional billing fields so it can diagnose partially applied billing migrations instead of failing on missing columns.

The dashboard should be reviewed after billing migrations, Stripe webhook configuration changes, failed payments, and scheduled billing-change deployments.

<!-- End of file. -->
'''
        write(dev_rel, dev_doc)
    else:
        print("[PASS] docs/dev/subscription_billing.md already mentions billing health dashboard")


def update_project_state() -> None:
    rel = "PROJECT_STATE.md"
    content = read(rel)
    marker = "## 2026-06-27 Platform Admin billing health dashboard"
    if marker in content:
        print("[PASS] PROJECT_STATE.md already records billing health dashboard")
        return

    addition = '''

## 2026-06-27 Platform Admin billing health dashboard

- Platform Admin includes `/platform/admin/billing-health`.
- `BillingHealthController` is read-only and schema-tolerant so it can diagnose
  partially applied billing migrations.
- The dashboard surfaces paid plans missing Stripe Price IDs, free plans with
  Stripe Price IDs, payment-pending tenants, past-due tenants, overdue
  scheduled changes, missing subscription item IDs, Stripe Billing Portal
  errors, failed webhooks, stuck processing webhooks, and billing schema
  readiness.
- Platform admin navigation includes `Billing Health`.

<!-- End of file. -->
'''
    write(rel, content.rstrip() + addition)


def main() -> None:
    ensure_prerequisites()
    backup()

    write_controller()
    patch_platform_routes()
    patch_admin_nav()
    write_static_test()
    patch_docs()
    update_project_state()
    update_route_fixture()

    for rel in [
        "app/Http/Controllers/Platform/Admin/BillingHealthController.php",
        "app/Http/Routes/platform.php",
        "app/Http/View/AdminLayout.php",
        "scripts/test/platform_billing_health_static.php",
    ]:
        run(["php", "-l", rel], f"PHP syntax: {rel}")

    run(["php", "scripts/test/platform_billing_health_static.php"], "platform billing health static test")

    for rel in [
        "scripts/test/subscription_billing_workflow_static.php",
        "scripts/test/subscription_billing_hardening_static.php",
        "scripts/test/subscription_billing_price_ids_static.php",
        "scripts/test/subscription_billing_portal_static.php",
        "scripts/test/subscription_billing_webhook_idempotency_static.php",
    ]:
        if project_path(rel).is_file():
            run(["php", rel], rel)

    if project_path("scripts/test/phase8_routing_static.php").is_file():
        run(["php", "scripts/test/phase8_routing_static.php"], "route inventory static test")

    print("")
    print("[PASS] Platform Admin billing health dashboard patch applied.")
    print("[INFO] Visit /platform/admin/billing-health after deploy.")
    print("[INFO] No migration is required for this dashboard pass.")
    print("[INFO] fix.txt was not modified.")


if __name__ == "__main__":
    main()

# End of file.
