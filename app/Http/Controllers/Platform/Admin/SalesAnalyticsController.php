<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Tenant\Sales\SalesRepository;

/**
 * Shows platform-wide sales analytics for ArtsFolio operators.
 */
final class SalesAnalyticsController
{
    public function __construct(
        private readonly RequirePlatformRole $roles,
        private readonly SalesRepository $sales,
    ) {}

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, ['platform_admin', 'admin', 'owner'])) {
            return Response::html('<h1>Forbidden</h1><p>Platform admin access required.</p>', 403);
        }

        $days = max(7, min(365, (int) ($_GET['days'] ?? 30)));
        $includeNoSales = isset($_GET['include_no_sales']) && (string) $_GET['include_no_sales'] === '1';
        $summary = $this->sales->platformSalesSummary($includeNoSales);
        $byDay = $this->sales->platformSalesByDay($days);
        $byTenant = $this->sales->platformSalesByTenant();

        $dayRows = '';
        foreach ($byDay as $row) {
            $dayRows .= '<tr><td>' . $this->e((string) $row['sale_day']) . '</td><td>' . (int) $row['order_count'] . '</td><td>' . $this->money((int) $row['gross_cents']) . '</td><td>' . $this->money((int) $row['commission_cents']) . '</td><td>' . $this->money((int) ($row['credit_card_fee_cents'] ?? 0)) . '</td><td>' . $this->money((int) ($row['seller_net_cents'] ?? 0)) . '</td></tr>';
        }
        if ($dayRows === '') {
            $dayRows = '<tr><td colspan="6">No paid sales in this range.</td></tr>';
        }

        $tenantRows = '';
        foreach ($byTenant as $row) {
            $tenantUrl = 'https://' . $this->e((string) $row['tenant_slug']) . '.artsfol.io/';
            $tenantRows .= '<tr><td><a href="' . $tenantUrl . '">' . $this->e((string) $row['tenant_name']) . '</a></td><td>' . (int) $row['order_count'] . '</td><td>' . $this->money((int) $row['gross_cents']) . '</td><td>' . $this->money((int) $row['commission_cents']) . '</td><td>' . $this->money((int) ($row['credit_card_fee_cents'] ?? 0)) . '</td><td>' . $this->money((int) ($row['seller_net_cents'] ?? 0)) . '</td></tr>';
        }
        if ($tenantRows === '') {
            $tenantRows = '<tr><td colspan="6">No paid tenant sales yet.</td></tr>';
        }

        $filter = $this->filterHtml($days, $includeNoSales);
        $workflow = $this->workflowList((array) $summary['workflow_counts']);
        $body = <<<HTML
{$filter}
<section class="admin-billing-summary af-sales-kpis">
  <div class="admin-panel"><p class="admin-muted">Paid orders</p><h2>{$this->e((string) $summary['order_count'])}</h2></div>
  <div class="admin-panel"><p class="admin-muted">Selling tenants</p><h2>{$this->e((string) $summary['tenant_count'])}</h2></div>
  <div class="admin-panel"><p class="admin-muted">Gross sales</p><h2>{$this->money((int) $summary['gross_cents'])}</h2></div>
  <div class="admin-panel"><p class="admin-muted">Commission</p><h2>{$this->money((int) $summary['commission_cents'])}</h2></div>
  <div class="admin-panel"><p class="admin-muted">Card fees</p><h2>{$this->money((int) ($summary['credit_card_fee_cents'] ?? 0))}</h2></div>
  <div class="admin-panel"><p class="admin-muted">Seller net</p><h2>{$this->money((int) ($summary['seller_net_cents'] ?? 0))}</h2></div>
</section>
<section class="admin-panel"><h2>Workflow status</h2><p class="admin-muted">Default view hides abandoned, unpaid, and other no-sale checkout rows.</p>{$workflow}</section>
<section class="admin-panel">
  <h2>Platform sales by day</h2>
  <p class="admin-muted">Paid sales for the last {$days} days.</p>
  <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Orders</th><th>Gross</th><th>Commission</th><th>Card fees</th><th>Seller net</th></tr></thead><tbody>{$dayRows}</tbody></table></div>
</section>
<section class="admin-panel">
  <h2>Sales by tenant</h2>
  <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Tenant</th><th>Orders</th><th>Gross</th><th>Commission</th><th>Card fees</th><th>Seller net</th></tr></thead><tbody>{$tenantRows}</tbody></table></div>
</section>
HTML;

        return Response::html(AdminLayout::render('Sales analytics', $body, 'sales_analytics'));
    }

    private function workflowList(array $counts): string
    {
        $items = '';
        foreach (['ordered', 'acknowledged', 'packed', 'shipped', 'refunded'] as $status) {
            $items .= '<li><strong>' . $this->e(ucfirst($status)) . ':</strong> ' . (int) ($counts[$status] ?? 0) . '</li>';
        }
        return '<ul class="af-sales-workflow-list">' . $items . '</ul>';
    }

    private function filterHtml(int $days, bool $includeNoSales): string
    {
        $checked = $includeNoSales ? ' checked' : '';
        return '<form class="admin-filter-bar" method="get" action="/platform/admin/sales/analytics"><label>Days <input type="number" min="7" max="365" name="days" value="' . $days . '"></label><label><input type="checkbox" name="include_no_sales" value="1"' . $checked . '> Include no-sale workflow rows</label><button type="submit">Apply filter</button><a href="/platform/admin/sales/analytics?days=' . $days . '">Paid sales only</a></form>';
    }

    private function money(int $cents): string { return '$' . number_format($cents / 100, 2); }
    private function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
}

// End of file.
