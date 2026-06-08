<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Request;
use App\Http\Response;
use App\Http\View\TenantAdminLayout;
use App\Platform\Tenancy\TenantContext;
use App\Support\Database;
use App\Tenant\Settings\TenantSettingsRepository;
use PDO;
use Throwable;

/**
 * Tenant admin dashboard with practical site, sales, and engagement signals.
 */
final class DashboardController
{
    private ?PDO $pdo = null;

    public function __construct(
        private readonly TenantSettingsRepository $settings,
    ) {
    }

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        $metrics = $this->tenantMetrics($tenant);
        $recentOrders = $this->recentOrderRows($tenant);
        $attentionRows = $this->attentionRows($tenant, $metrics);
        $recentMessages = $this->recentMessageRows($tenant);
        $planName = $this->e($metrics['plan_name']);
        $salesState = $this->e($metrics['sales_state']);

        $body = <<<HTML
<p class="admin-muted">Manage the public site, artwork catalog, engagement, sales workflow, and reporting. This dashboard favors things that need action over wallpaper numbers.</p>

<section class="dashboard-metric-grid" aria-label="Tenant summary metrics">
    {$this->metricCard('Published artworks', $metrics['published_artworks'], $metrics['artwork_detail'], '/admin/artworks')}
    {$this->metricCard('For sale', $metrics['for_sale_artworks'], $metrics['sales_inventory_detail'], '/admin/artworks')}
    {$this->metricCard('30-day views', $metrics['views_30d'], $metrics['views_detail'], '/admin/stats')}
    {$this->metricCard('Subscribers', $metrics['subscribers'], $metrics['subscriber_detail'], '/admin/email-signups')}
    {$this->metricCard('Open messages', $metrics['open_messages'], $metrics['message_detail'], '/admin/contact-messages')}
    {$this->metricCard('Open orders', $metrics['open_orders'], $metrics['order_detail'], '/admin/sales')}
</section>

<section class="admin-panel dashboard-plan-panel">
    <div><p class="admin-muted">Current plan</p><h2>{$planName}</h2><p>{$salesState}</p></div>
    <div><p class="admin-muted">30-day sales</p><h2>{$this->e($metrics['sales_30d'])}</h2><p>Seller net {$this->e($metrics['seller_net_30d'])} after commission and card charges.</p></div>
    <div><p class="admin-muted">Next useful action</p><h2>{$this->e($metrics['next_action_title'])}</h2><p>{$this->e($metrics['next_action_detail'])}</p></div>
</section>

<div class="dashboard-split-grid">
    <section class="admin-panel">
        <div class="dashboard-section-head"><h2>Needs attention</h2><a href="/admin/billing">View billing</a></div>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Area</th><th>Signal</th><th>Action</th></tr></thead><tbody>{$attentionRows}</tbody></table></div>
    </section>
    <section class="admin-panel">
        <div class="dashboard-section-head"><h2>Recent orders</h2><a href="/admin/sales">View sales</a></div>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>When</th><th>Order</th><th>Status</th><th>Gross</th><th>Net</th></tr></thead><tbody>{$recentOrders}</tbody></table></div>
    </section>
</div>

<div class="dashboard-split-grid">
    <section class="admin-panel">
        <div class="dashboard-section-head"><h2>Recent contact messages</h2><a href="/admin/contact-messages">View messages</a></div>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>When</th><th>From</th><th>Subject</th><th>Status</th></tr></thead><tbody>{$recentMessages}</tbody></table></div>
    </section>
    <section class="admin-panel">
        <div class="dashboard-section-head"><h2>Workbench</h2><a href="/admin/getting-started">Getting started</a></div>
        <div class="admin-card-grid dashboard-action-grid">
            <a class="dashboard-card" href="/admin/artwork/upload"><h3>Upload work</h3><p>Add portfolio or site images and set sales details.</p></a>
            <a class="dashboard-card" href="/admin/settings"><h3>Style site</h3><p>Branding, page settings, top bar, backgrounds, and navigation.</p></a>
            <a class="dashboard-card" href="/admin/sales"><h3>Process sales</h3><p>Acknowledge, pack, ship, and track customer orders.</p></a>
            <a class="dashboard-card" href="/admin/stats"><h3>Study traffic</h3><p>Find what visitors view, where they come from, and what earns attention.</p></a>
        </div>
    </section>
</div>
HTML;

        return Response::html((new TenantAdminLayout($this->settings))->render($tenant, 'Tenant Admin', $body, 'dashboard'));
    }

    /**
     * Builds dashboard metrics from tenant-scoped tables. Optional tables are
     * guarded so the page remains useful during incremental migrations.
     *
     * @return array<string,string|int>
     */
    private function tenantMetrics(TenantContext $tenant): array
    {
        $tenantId = $tenant->tenantId;
        $published = $this->tenantScalarInt('SELECT COUNT(*) FROM artworks WHERE tenant_id = :tenant_id AND status = "published"', $tenantId);
        $drafts = $this->tenantScalarInt('SELECT COUNT(*) FROM artworks WHERE tenant_id = :tenant_id AND status = "draft"', $tenantId);
        $forSale = $this->tenantScalarInt('SELECT COUNT(*) FROM artworks WHERE tenant_id = :tenant_id AND status = "published" AND sale_status = "for_sale"', $tenantId);
        $lowStock = $this->tenantScalarInt('SELECT COUNT(*) FROM artworks WHERE tenant_id = :tenant_id AND status = "published" AND sale_status = "for_sale" AND is_one_off = 0 AND inventory_quantity <= 2', $tenantId);
        $views30 = $this->tenantScalarInt('SELECT COUNT(*) FROM analytics_events WHERE tenant_id = :tenant_id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)', $tenantId);
        $topPath = $this->topPath($tenant);
        $subscribers = $this->tenantScalarInt('SELECT COUNT(*) FROM email_signups WHERE tenant_id = :tenant_id AND consent_status IN ("pending", "confirmed")', $tenantId);
        if ($subscribers === 0) {
            $subscribers = $this->tenantScalarInt('SELECT COUNT(*) FROM newsletter_subscribers WHERE tenant_id = :tenant_id AND status = "subscribed"', $tenantId);
        }
        $openMessages = $this->tenantScalarInt('SELECT COUNT(*) FROM contact_messages WHERE tenant_id = :tenant_id AND status IN ("new", "read")', $tenantId);
        $openOrders = $this->tenantScalarInt('SELECT COUNT(*) FROM sales_orders WHERE tenant_id = :tenant_id AND workflow_status NOT IN ("shipped", "cancelled", "refunded")', $tenantId);
        $gross30 = $this->tenantScalarInt('SELECT COALESCE(SUM(total_cents), 0) FROM sales_orders WHERE tenant_id = :tenant_id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND payment_status IN ("paid", "complete", "succeeded")', $tenantId);
        $net30 = $this->tenantScalarInt('SELECT COALESCE(SUM(seller_net_cents), 0) FROM sales_orders WHERE tenant_id = :tenant_id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND payment_status IN ("paid", "complete", "succeeded")', $tenantId);
        $plan = $this->currentPlan($tenant);
        $allowSales = (int) ($plan['allow_sales'] ?? 0) === 1;
        $stripeAccount = trim((string) $this->settings->get($tenant, 'stripe_connected_account_id', ''));
        $next = $this->nextAction($drafts, $openMessages, $openOrders, $forSale, $allowSales, $stripeAccount);

        return [
            'published_artworks' => $this->number($published),
            'artwork_detail' => $this->number($drafts) . ' drafts waiting',
            'for_sale_artworks' => $this->number($forSale),
            'sales_inventory_detail' => $this->number($lowStock) . ' low-stock multiples',
            'views_30d' => $this->number($views30),
            'views_detail' => $topPath !== '' ? 'Top path: ' . $topPath : 'No recent traffic path yet',
            'subscribers' => $this->number($subscribers),
            'subscriber_detail' => 'Tenant mailing list audience',
            'open_messages' => $this->number($openMessages),
            'message_detail' => 'Contact follow-up queue',
            'open_orders' => $this->number($openOrders),
            'order_detail' => 'Ordered, acknowledged, or packed',
            'sales_30d' => $this->money($gross30),
            'seller_net_30d' => $this->money($net30),
            'plan_name' => (string) ($plan['name'] ?? 'Unassigned plan'),
            'sales_state' => $allowSales ? ($stripeAccount !== '' ? 'Online checkout is available and Stripe is configured.' : 'Online checkout is plan-enabled, but Stripe is not configured yet.') : 'Online checkout is not included in the selected plan.',
            'next_action_title' => $next['title'],
            'next_action_detail' => $next['detail'],
        ];
    }

    private function recentOrderRows(TenantContext $tenant): string
    {
        if (!$this->tableExists('sales_orders')) {
            return $this->emptyRow(5, 'Sales tables are not installed yet.');
        }

        try {
            $stmt = $this->pdo()->prepare('SELECT created_at, order_number, workflow_status, payment_status, total_cents, seller_net_cents FROM sales_orders WHERE tenant_id = :tenant_id ORDER BY created_at DESC LIMIT 6');
            $stmt->execute(['tenant_id' => $tenant->tenantId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            return $this->emptyRow(5, 'Sales dashboard query failed: ' . $exception->getMessage());
        }

        if ($rows === []) {
            return $this->emptyRow(5, 'No orders yet.');
        }

        $html = '';
        foreach ($rows as $row) {
            $status = (string) ($row['payment_status'] ?? '') . ' · ' . (string) ($row['workflow_status'] ?? '');
            $html .= '<tr><td>' . $this->shortDate((string) ($row['created_at'] ?? '')) . '</td><td>' . $this->e((string) ($row['order_number'] ?? '')) . '</td><td>' . $this->e($status) . '</td><td>' . $this->money((int) ($row['total_cents'] ?? 0)) . '</td><td>' . $this->money((int) ($row['seller_net_cents'] ?? 0)) . '</td></tr>';
        }
        return $html;
    }

    /** @param array<string,string|int> $metrics */
    private function attentionRows(TenantContext $tenant, array $metrics): string
    {
        $rows = [];
        $drafts = $this->tenantScalarInt('SELECT COUNT(*) FROM artworks WHERE tenant_id = :tenant_id AND status = "draft"', $tenant->tenantId);
        if ($drafts > 0) {
            $rows[] = ['Artwork', $this->number($drafts) . ' draft artworks', '<a href="/admin/artworks">Review drafts</a>'];
        }
        $unshipped = $this->tenantScalarInt('SELECT COUNT(*) FROM sales_orders WHERE tenant_id = :tenant_id AND workflow_status IN ("ordered", "acknowledged", "packed") AND payment_status IN ("paid", "complete", "succeeded")', $tenant->tenantId);
        if ($unshipped > 0) {
            $rows[] = ['Sales', $this->number($unshipped) . ' paid orders need workflow updates', '<a href="/admin/sales">Open sales</a>'];
        }
        $openMessages = (int) str_replace(',', '', (string) $metrics['open_messages']);
        if ($openMessages > 0) {
            $rows[] = ['Messages', $this->number($openMessages) . ' open contact messages', '<a href="/admin/contact-messages">Read messages</a>'];
        }
        $stripeAccount = trim((string) $this->settings->get($tenant, 'stripe_connected_account_id', ''));
        $plan = $this->currentPlan($tenant);
        if ((int) ($plan['allow_sales'] ?? 0) === 1 && $stripeAccount === '') {
            $rows[] = ['Checkout', 'Sales are enabled but Stripe is not connected', '<a href="/admin/settings">Add Stripe account</a>'];
        }
        if ($rows === []) {
            $rows[] = ['Site', 'No urgent dashboard warnings', '<a href="/admin/stats">Review stats</a>'];
        }

        $html = '';
        foreach ($rows as [$area, $signal, $action]) {
            $html .= '<tr><td>' . $this->e($area) . '</td><td>' . $this->e($signal) . '</td><td>' . $action . '</td></tr>';
        }
        return $html;
    }

    private function recentMessageRows(TenantContext $tenant): string
    {
        if (!$this->tableExists('contact_messages')) {
            return $this->emptyRow(4, 'Contact message table is not installed.');
        }

        try {
            $stmt = $this->pdo()->prepare('SELECT created_at, name, email, subject, status FROM contact_messages WHERE tenant_id = :tenant_id ORDER BY created_at DESC LIMIT 6');
            $stmt->execute(['tenant_id' => $tenant->tenantId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            return $this->emptyRow(4, 'Contact message dashboard query failed: ' . $exception->getMessage());
        }

        if ($rows === []) {
            return $this->emptyRow(4, 'No contact messages yet.');
        }

        $html = '';
        foreach ($rows as $row) {
            $from = trim((string) ($row['name'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            $label = $from !== '' ? $from : ($email !== '' ? $email : 'Unknown');
            $html .= '<tr><td>' . $this->shortDate((string) ($row['created_at'] ?? '')) . '</td><td>' . $this->e($label) . '</td><td>' . $this->e((string) ($row['subject'] ?? '')) . '</td><td>' . $this->e((string) ($row['status'] ?? '')) . '</td></tr>';
        }
        return $html;
    }

    /** @return array<string,string> */
    private function nextAction(int $drafts, int $openMessages, int $openOrders, int $forSale, bool $allowSales, string $stripeAccount): array
    {
        if ($openOrders > 0) {
            return ['title' => 'Process orders', 'detail' => 'Paid orders should be acknowledged, packed, and shipped promptly.'];
        }
        if ($openMessages > 0) {
            return ['title' => 'Reply to messages', 'detail' => 'Contact messages are warm leads, not attic boxes.'];
        }
        if ($allowSales && $stripeAccount === '') {
            return ['title' => 'Connect Stripe', 'detail' => 'Your plan can sell online, but checkout needs a connected Stripe account.'];
        }
        if ($allowSales && $forSale === 0) {
            return ['title' => 'Mark work for sale', 'detail' => 'Add prices and inventory to sell from artwork detail pages.'];
        }
        if ($drafts > 0) {
            return ['title' => 'Publish drafts', 'detail' => 'Draft artworks do not help the public site until they are published.'];
        }
        return ['title' => 'Review analytics', 'detail' => 'Look for which pages and artworks are earning attention.'];
    }

    private function currentPlan(TenantContext $tenant): array
    {
        try {
            $stmt = $this->pdo()->prepare('SELECT p.* FROM tenant_plan_assignments tpa JOIN plans p ON p.id = tpa.plan_id WHERE tpa.tenant_id = :tenant_id ORDER BY tpa.id DESC LIMIT 1');
            $stmt->execute(['tenant_id' => $tenant->tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        } catch (Throwable) {
        }

        $slug = (string) $this->settings->get($tenant, 'billing_plan', 'free');
        try {
            $stmt = $this->pdo()->prepare('SELECT * FROM plans WHERE slug = :slug LIMIT 1');
            $stmt->execute(['slug' => $slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        } catch (Throwable) {
        }

        return ['name' => 'Unassigned plan', 'allow_sales' => 0];
    }

    private function topPath(TenantContext $tenant): string
    {
        try {
            $stmt = $this->pdo()->prepare('SELECT path, COUNT(*) AS c FROM analytics_events WHERE tenant_id = :tenant_id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY path ORDER BY c DESC LIMIT 1');
            $stmt->execute(['tenant_id' => $tenant->tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (string) ($row['path'] ?? '') : '';
        } catch (Throwable) {
            return '';
        }
    }

    private function tenantScalarInt(string $sql, int $tenantId): int
    {
        try {
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute(['tenant_id' => $tenantId]);
            $value = $stmt->fetchColumn();
            return $value === false || $value === null ? 0 : (int) $value;
        } catch (Throwable) {
            return 0;
        }
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

    private function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = Database::connect(dirname(__DIR__, 6));
        }
        return $this->pdo;
    }

    private function metricCard(string $label, string|int $value, string $detail, string $href): string
    {
        return '<a class="dashboard-metric-card" href="' . $this->e($href) . '"><span>' . $this->e($label) . '</span><strong>' . $this->e((string) $value) . '</strong><small>' . $this->e($detail) . '</small></a>';
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
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
