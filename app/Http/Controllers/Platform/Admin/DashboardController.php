<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Http\View\ErrorPage;
use App\Platform\Membership\Roles;
use App\Support\Database;
use PDO;
use Throwable;

/**
 * Platform admin dashboard with useful operating and business signals.
 */
final class DashboardController
{
    private ?PDO $pdo = null;

    public function __construct(private readonly RequirePlatformRole $roles)
    {
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $email = AdminLayout::escape((string) ($currentUser['email'] ?? ''));
        $metrics = $this->platformMetrics();
        $salesRows = $this->recentSalesRows();
        $tenantRows = $this->recentTenantRows();
        $jobRows = $this->jobRows();
        $planRows = $this->planRows();

        $body = <<<HTML
<p class="admin-muted">Signed in as {$email}. This dashboard shows platform health, tenant growth, sales economics, and work that needs attention.</p>

<section class="dashboard-metric-grid" aria-label="Platform summary metrics">
    {$this->metricCard('Tenants', $metrics['tenants_total'], $metrics['tenants_detail'], '/platform/admin/tenants')}
    {$this->metricCard('Paid-capable tenants', $metrics['paid_tenants'], $metrics['paid_detail'], '/platform/admin/tenants')}
    {$this->metricCard('30-day GMV', $this->money((int) $metrics['gmv_30d']), $metrics['sales_detail'], '/platform/admin/sales/analytics')}
    {$this->metricCard('30-day commission', $this->money((int) $metrics['commission_30d']), $metrics['commission_detail'], '/platform/admin/sales/analytics')}
    {$this->metricCard('Open platform contacts', $metrics['open_contacts'], $metrics['contact_detail'], '/platform/admin/contacts')}
    {$this->metricCard('Queued / failed jobs', $metrics['jobs_attention'], $metrics['jobs_detail'], '/platform/admin/jobs')}
</section>

<div class="dashboard-split-grid">
    <section class="admin-panel">
        <div class="dashboard-section-head"><h2>Recent sales</h2>


<a href="/platform/admin/sales">View sales</a></div>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>When</th><th>Tenant</th><th>Order</th><th>Status</th><th>Gross</th><th>Seller net</th></tr></thead><tbody>{$salesRows}</tbody></table></div>
    </section>
    <section class="admin-panel">
        <div class="dashboard-section-head"><h2>Recent tenants</h2><a href="/platform/admin/tenants">View tenants</a></div>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Tenant</th><th>Status</th><th>Plan</th><th>Created</th></tr></thead><tbody>{$tenantRows}</tbody></table></div>
    </section>
</div>

<div class="dashboard-split-grid">
    <section class="admin-panel">
        <div class="dashboard-section-head"><h2>Plans and selling controls</h2><a href="/platform/admin/pricing">Edit pricing</a></div>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Plan</th><th>Monthly</th><th>Sales</th><th>Commission</th><th>Card fee</th></tr></thead><tbody>{$planRows}</tbody></table></div>
    </section>
    <section class="admin-panel">
        <div class="dashboard-section-head"><h2>Background jobs</h2><a href="/platform/admin/jobs">Inspect jobs</a></div>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Status</th><th>Count</th><th>Oldest</th></tr></thead><tbody>{$jobRows}</tbody></table></div>
    </section>
</div>

<section class="admin-panel">
    <h2>Common workbench</h2>
    <div class="admin-card-grid dashboard-action-grid">
        <a class="admin-card" href="/platform/admin/tenants"><h3>Tenants</h3><p>Review tenant status, plans, complimentary flags, and custom domains.</p></a>
        <a class="admin-card" href="/platform/admin/pricing"><h3>Pricing</h3><p>Adjust tiers, usage limits, sales eligibility, commission, and card fees.</p></a>
        <a class="admin-card" href="/platform/admin/stats"><h3>Stats</h3><p>Review platform-level traffic and location signals.</p></a>
        <a class="admin-card" href="/platform/admin/email-outbox"><h3>Email Outbox</h3><p>Inspect branded platform email, invites, reminders, and delivery status.</p></a>
        <a class="admin-card" href="/platform/admin/domains"><h3>Domains</h3><p>Verify DNS and custom-domain routing for tenants.</p></a>
        <a class="admin-card" href="/platform/admin/audit-log"><h3>Audit Log</h3><p>Review security-relevant platform and tenant administration events.</p></a>
    </div>
</section>
HTML;

        return Response::html(AdminLayout::render(title: 'Platform Admin', body: $body, active: 'dashboard'));
    }

    /**
     * Summarizes platform metrics while degrading gracefully when an optional
     * sales or engagement table is missing during rolling deploys.
     *
     * @return array<string,string|int>
     */
    private function platformMetrics(): array
    {
        $tenantsTotal = $this->scalarInt("SELECT COUNT(*) FROM tenants WHERE status IS NULL OR status <> 'deleted'");
        $activeTenants = $this->scalarInt("SELECT COUNT(*) FROM tenants WHERE status IS NULL OR status IN ('pending_setup','trial','active')");
        $complementaryTenants = $this->columnExists('tenants', 'complementary') ? $this->scalarInt('SELECT COUNT(*) FROM tenants WHERE complementary = 1 AND (status IS NULL OR status <> "deleted")') : 0;
        $paidTenants = $this->scalarInt('SELECT COUNT(DISTINCT tpa.tenant_id) FROM tenant_plan_assignments tpa JOIN plans p ON p.id = tpa.plan_id JOIN tenants t ON t.id = tpa.tenant_id WHERE (t.status IS NULL OR t.status <> "deleted") AND p.monthly_price_cents > 0');
        $gmv30d = $this->scalarInt('SELECT COALESCE(SUM(total_cents), 0) FROM sales_orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND payment_status IN ("paid", "complete", "succeeded")');
        $commission30d = $this->scalarInt('SELECT COALESCE(SUM(commission_cents), 0) FROM sales_orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND payment_status IN ("paid", "complete", "succeeded")');
        $sellerNet30d = $this->scalarInt('SELECT COALESCE(SUM(seller_net_cents), 0) FROM sales_orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND payment_status IN ("paid", "complete", "succeeded")');
        $orders30d = $this->scalarInt('SELECT COUNT(*) FROM sales_orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)');
        $openContacts = $this->scalarInt("SELECT COUNT(*) FROM contact_messages WHERE tenant_id IS NULL AND status IN ('new','read')");
        $jobsQueued = $this->scalarInt("SELECT COUNT(*) FROM background_jobs WHERE status IN ('queued','running')");
        $jobsFailed = $this->scalarInt("SELECT COUNT(*) FROM background_jobs WHERE status = 'failed'");

        return [
            'tenants_total' => $this->number($tenantsTotal),
            'tenants_detail' => $this->number($activeTenants) . ' active/trial · ' . $this->number($complementaryTenants) . ' complimentary',
            'paid_tenants' => $this->number($paidTenants),
            'paid_detail' => 'Tenants assigned to paid monthly plans',
            'gmv_30d' => $gmv30d,
            'sales_detail' => $this->number($orders30d) . ' orders in the last 30 days',
            'commission_30d' => $commission30d,
            'commission_detail' => 'Seller net ' . $this->money($sellerNet30d) . ' in the last 30 days',
            'open_contacts' => $this->number($openContacts),
            'contact_detail' => 'Public artsfol.io contact follow-up queue',
            'jobs_attention' => $this->number($jobsQueued) . ' / ' . $this->number($jobsFailed),
            'jobs_detail' => 'Queued/running jobs · failed jobs',
        ];
    }

    private function recentSalesRows(): string
    {
        if (!$this->tableExists('sales_orders')) {
            return $this->emptyRow(6, 'Sales tables are not installed yet.');
        }

        try {
            $stmt = $this->pdo()->query('SELECT so.created_at, so.order_number, so.workflow_status, so.payment_status, so.total_cents, so.seller_net_cents, t.name AS tenant_name, t.slug AS tenant_slug FROM sales_orders so JOIN tenants t ON t.id = so.tenant_id ORDER BY so.created_at DESC LIMIT 8');
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $exception) {
            return $this->emptyRow(6, 'Sales dashboard query failed: ' . $exception->getMessage());
        }

        if ($rows === []) {
            return $this->emptyRow(6, 'No sales orders yet.');
        }

        $html = '';
        foreach ($rows as $row) {
            $tenantSlug = $this->e((string) ($row['tenant_slug'] ?? ''));
            $tenantName = $this->e((string) ($row['tenant_name'] ?? 'Tenant'));
            $order = $this->e((string) ($row['order_number'] ?? ''));
            $status = $this->e((string) ($row['payment_status'] ?? '') . ' · ' . (string) ($row['workflow_status'] ?? ''));
            $html .= '<tr><td>' . $this->shortDate((string) ($row['created_at'] ?? '')) . '</td><td><a href="/platform/admin/tenants?search=' . rawurlencode($tenantSlug) . '">' . $tenantName . '</a></td><td>' . $order . '</td><td>' . $status . '</td><td>' . $this->money((int) ($row['total_cents'] ?? 0)) . '</td><td>' . $this->money((int) ($row['seller_net_cents'] ?? 0)) . '</td></tr>';
        }

        return $html;
    }

    private function recentTenantRows(): string
    {
        try {
            $planSelect = $this->tableExists('tenant_plan_assignments') ? 'p.name AS plan_name,' : "'' AS plan_name,";
            $join = $this->tableExists('tenant_plan_assignments') ? 'LEFT JOIN tenant_plan_assignments tpa ON tpa.tenant_id = t.id LEFT JOIN plans p ON p.id = tpa.plan_id' : '';
            $stmt = $this->pdo()->query("SELECT t.name, t.slug, t.status, t.created_at, {$planSelect} COALESCE(t.complementary, 0) AS complementary FROM tenants t {$join} WHERE t.status IS NULL OR t.status <> 'deleted' ORDER BY t.created_at DESC LIMIT 8");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $exception) {
            return $this->emptyRow(4, 'Tenant dashboard query failed: ' . $exception->getMessage());
        }

        if ($rows === []) {
            return $this->emptyRow(4, 'No tenant records found.');
        }

        $html = '';
        foreach ($rows as $row) {
            $name = $this->e((string) ($row['name'] ?? 'Tenant'));
            $slug = $this->e((string) ($row['slug'] ?? ''));
            $status = $this->e((string) ($row['status'] ?? ''));
            $plan = trim((string) ($row['plan_name'] ?? ''));
            if ((int) ($row['complementary'] ?? 0) === 1) {
                $plan = ($plan !== '' ? $plan : 'Assigned plan') . ' · complementary';
            }
            $html .= '<tr><td><a href="/platform/admin/tenants?search=' . rawurlencode($slug) . '">' . $name . '</a><br><span class="admin-muted">' . $slug . '</span></td><td>' . $status . '</td><td>' . $this->e($plan !== '' ? $plan : 'Unassigned') . '</td><td>' . $this->shortDate((string) ($row['created_at'] ?? '')) . '</td></tr>';
        }

        return $html;
    }

    private function jobRows(): string
    {
        if (!$this->tableExists('background_jobs')) {
            return $this->emptyRow(3, 'Background job table is not installed.');
        }

        try {
            $stmt = $this->pdo()->query('SELECT status, COUNT(*) AS c, MIN(created_at) AS oldest FROM background_jobs GROUP BY status ORDER BY FIELD(status, "failed", "running", "queued", "complete", "cancelled"), status');
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $exception) {
            return $this->emptyRow(3, 'Background job dashboard query failed: ' . $exception->getMessage());
        }

        if ($rows === []) {
            return $this->emptyRow(3, 'No background jobs recorded.');
        }

        $html = '';
        foreach ($rows as $row) {
            $html .= '<tr><td>' . $this->e((string) ($row['status'] ?? '')) . '</td><td>' . $this->number((int) ($row['c'] ?? 0)) . '</td><td>' . $this->shortDate((string) ($row['oldest'] ?? '')) . '</td></tr>';
        }
        return $html;
    }

    private function planRows(): string
    {
        if (!$this->tableExists('plans')) {
            return $this->emptyRow(5, 'Plans table is not installed.');
        }

        $commissionSelect = $this->columnExists('plans', 'platform_commission_basis_points')
            ? 'platform_commission_basis_points'
            : ((string) $this->platformCommissionBasisPoints()) . ' AS platform_commission_basis_points';

        try {
            $stmt = $this->pdo()->query('SELECT name, monthly_price_cents, allow_sales, ' . $commissionSelect . ', credit_card_fee_basis_points, credit_card_fixed_fee_cents FROM plans WHERE is_active = 1 ORDER BY display_order ASC, monthly_price_cents ASC, id ASC LIMIT 8');
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $exception) {
            return $this->emptyRow(5, 'Plan dashboard query failed: ' . $exception->getMessage());
        }

        if ($rows === []) {
            return $this->emptyRow(5, 'No active plans found.');
        }

        $html = '';
        foreach ($rows as $row) {
            $commission = $this->basisPoints((int) ($row['platform_commission_basis_points'] ?? 0));
            $card = $this->basisPoints((int) ($row['credit_card_fee_basis_points'] ?? 0)) . ' + ' . $this->money((int) ($row['credit_card_fixed_fee_cents'] ?? 0));
            $html .= '<tr><td>' . $this->e((string) ($row['name'] ?? 'Plan')) . '</td><td>' . $this->money((int) ($row['monthly_price_cents'] ?? 0)) . '</td><td>' . ((int) ($row['allow_sales'] ?? 0) === 1 ? 'Yes' : 'No') . '</td><td>' . $commission . '</td><td>' . $card . '</td></tr>';
        }
        return $html;
    }

    private function platformCommissionBasisPoints(): int
    {
        try {
            $stmt = $this->pdo()->prepare('SELECT setting_value FROM platform_settings WHERE setting_key = :setting_key LIMIT 1');
            $stmt->execute(['setting_key' => 'platform_sales_commission_basis_points']);
            $value = $stmt->fetchColumn();
            return $value === false || $value === null ? 0 : max(0, (int) $value);
        } catch (Throwable) {
            return 0;
        }
    }

    private function metricCard(string $label, string|int $value, string $detail, string $href): string
    {
        return '<a class="dashboard-metric-card" href="' . $this->e($href) . '"><span>' . $this->e($label) . '</span><strong>' . $this->e((string) $value) . '</strong><small>' . $this->e($detail) . '</small></a>';
    }

    private function tableExists(string $table): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return false;
        }

        try {
            $stmt = $this->pdo()->query('SHOW TABLES LIKE ' . $this->pdo()->quote($table));
            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
            return false;
        }

        try {
            $stmt = $this->pdo()->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . $this->pdo()->quote($column));
            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    private function scalarInt(string $sql): int
    {
        try {
            $value = $this->pdo()->query($sql)?->fetchColumn();
            return $value === false || $value === null ? 0 : (int) $value;
        } catch (Throwable) {
            return 0;
        }
    }

    private function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = Database::connect(dirname(__DIR__, 5));
        }
        return $this->pdo;
    }

    private function emptyRow(int $columns, string $message): string
    {
        return '<tr><td colspan="' . $columns . '"><span class="admin-muted">' . $this->e($message) . '</span></td></tr>';
    }

    private function money(int $cents): string
    {
        return '$' . number_format($cents / 100, 2);
    }

    private function number(int $value): string
    {
        return number_format($value);
    }

    private function basisPoints(int $basisPoints): string
    {
        return rtrim(rtrim(number_format($basisPoints / 100, 2), '0'), '.') . '%';
    }

    private function shortDate(string $date): string
    {
        if ($date === '') {
            return '—';
        }
        $timestamp = strtotime($date);
        return $timestamp ? date('M j, Y', $timestamp) : $this->e($date);
    }

    private function e(string $value): string
    {
        return AdminLayout::escape($value);
    }
}

// End of file.
