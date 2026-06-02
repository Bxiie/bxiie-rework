<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Tenancy\TenantContext;
use App\Tenant\Sales\SalesRepository;

/**
 * Shows tenant-scoped sales analytics for artists and tenant administrators.
 */
final class SalesAnalyticsController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly SalesRepository $sales,
    ) {}

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $days = max(7, min(365, (int) ($_GET['days'] ?? 30)));
        $summary = $this->sales->tenantSalesSummary($tenant);
        $byDay = $this->sales->tenantSalesByDay($tenant, $days);
        $bestSellers = $this->sales->tenantBestSellers($tenant);

        $dayRows = $this->dayRows($byDay, false);
        $sellerRows = '';
        foreach ($bestSellers as $item) {
            $sellerRows .= '<tr><td>' . $this->e((string) $item['title_snapshot']) . '</td><td>' . (int) $item['units_sold'] . '</td><td>' . $this->money((int) $item['gross_cents']) . '</td></tr>';
        }
        if ($sellerRows === '') {
            $sellerRows = '<tr><td colspan="3">No paid sales yet.</td></tr>';
        }

        $workflow = $this->workflowList((array) $summary['workflow_counts']);
        $body = <<<HTML
<section class="admin-billing-summary af-sales-kpis">
  <div class="admin-panel"><p class="admin-muted">Paid orders</p><h2>{$this->e((string) $summary['order_count'])}</h2></div>
  <div class="admin-panel"><p class="admin-muted">Gross sales</p><h2>{$this->money((int) $summary['gross_cents'])}</h2></div>
  <div class="admin-panel"><p class="admin-muted">Platform commission</p><h2>{$this->money((int) $summary['commission_cents'])}</h2></div>
  <div class="admin-panel"><p class="admin-muted">Card fees</p><h2>{$this->money((int) ($summary['credit_card_fee_cents'] ?? 0))}</h2></div>
  <div class="admin-panel"><p class="admin-muted">Seller net</p><h2>{$this->money((int) ($summary['seller_net_cents'] ?? 0))}</h2></div>
  <div class="admin-panel"><p class="admin-muted">Average order</p><h2>{$this->money((int) $summary['average_order_cents'])}</h2></div>
</section>
<section class="admin-panel">
  <h2>Workflow status</h2>
  {$workflow}
</section>
<section class="admin-panel">
  <h2>Sales by day</h2>
  <p class="admin-muted">Paid sales for the last {$days} days.</p>
  <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Orders</th><th>Gross</th><th>Commission</th><th>Card fees</th><th>Seller net</th></tr></thead><tbody>{$dayRows}</tbody></table></div>
</section>
<section class="admin-panel">
  <h2>Best sellers</h2>
  <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Artwork</th><th>Units</th><th>Gross</th></tr></thead><tbody>{$sellerRows}</tbody></table></div>
</section>
HTML;

        return Response::html(AdminLayout::render('Sales analytics', $body, 'sales'));
    }

    private function dayRows(array $rows, bool $includeCommission): string
    {
        $html = '';
        foreach ($rows as $row) {
            $html .= '<tr><td>' . $this->e((string) $row['sale_day']) . '</td><td>' . (int) $row['order_count'] . '</td><td>' . $this->money((int) $row['gross_cents']) . '</td><td>' . $this->money((int) $row['commission_cents']) . '</td><td>' . $this->money((int) ($row['credit_card_fee_cents'] ?? 0)) . '</td><td>' . $this->money((int) ($row['seller_net_cents'] ?? 0)) . '</td></tr>';
        }
        return $html !== '' ? $html : '<tr><td colspan="6">No paid sales in this range.</td></tr>';
    }

    private function workflowList(array $counts): string
    {
        $items = '';
        foreach (['ordered', 'acknowledged', 'packed', 'shipped'] as $status) {
            $items .= '<li><strong>' . $this->e(ucfirst($status)) . ':</strong> ' . (int) ($counts[$status] ?? 0) . '</li>';
        }
        return '<ul class="af-sales-workflow-list">' . $items . '</ul>';
    }

    private function money(int $cents): string { return '$' . number_format($cents / 100, 2); }
    private function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
}

// End of file.
