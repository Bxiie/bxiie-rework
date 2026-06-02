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
        $summary = $this->sales->platformSalesSummary();
        $byDay = $this->sales->platformSalesByDay($days);
        $byTenant = $this->sales->platformSalesByTenant();

        $dayRows = '';
        foreach ($byDay as $row) {
            $dayRows .= '<tr><td>' . $this->e((string) $row['sale_day']) . '</td><td>' . (int) $row['order_count'] . '</td><td>' . $this->money((int) $row['gross_cents']) . '</td><td>' . $this->money((int) $row['commission_cents']) . '</td></tr>';
        }
        if ($dayRows === '') {
            $dayRows = '<tr><td colspan="4">No paid sales in this range.</td></tr>';
        }

        $tenantRows = '';
        foreach ($byTenant as $row) {
            $tenantUrl = 'https://' . $this->e((string) $row['tenant_slug']) . '.artsfol.io/';
            $tenantRows .= '<tr><td><a href="' . $tenantUrl . '">' . $this->e((string) $row['tenant_name']) . '</a></td><td>' . (int) $row['order_count'] . '</td><td>' . $this->money((int) $row['gross_cents']) . '</td><td>' . $this->money((int) $row['commission_cents']) . '</td></tr>';
        }
        if ($tenantRows === '') {
            $tenantRows = '<tr><td colspan="4">No paid tenant sales yet.</td></tr>';
        }

        $workflow = $this->workflowList((array) $summary['workflow_counts']);
        $body = <<<HTML
<section class="admin-billing-summary af-sales-kpis">
  <div class="admin-panel"><p class="admin-muted">Paid orders</p><h2>{$this->e((string) $summary['order_count'])}</h2></div>
  <div class="admin-panel"><p class="admin-muted">Selling tenants</p><h2>{$this->e((string) $summary['tenant_count'])}</h2></div>
  <div class="admin-panel"><p class="admin-muted">Gross sales</p><h2>{$this->money((int) $summary['gross_cents'])}</h2></div>
  <div class="admin-panel"><p class="admin-muted">Commission</p><h2>{$this->money((int) $summary['commission_cents'])}</h2></div>
</section>
<section class="admin-panel"><h2>Workflow status</h2>{$workflow}</section>
<section class="admin-panel">
  <h2>Platform sales by day</h2>
  <p class="admin-muted">Paid sales for the last {$days} days.</p>
  <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Orders</th><th>Gross</th><th>Commission</th></tr></thead><tbody>{$dayRows}</tbody></table></div>
</section>
<section class="admin-panel">
  <h2>Sales by tenant</h2>
  <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Tenant</th><th>Orders</th><th>Gross</th><th>Commission</th></tr></thead><tbody>{$tenantRows}</tbody></table></div>
</section>
HTML;

        return Response::html(AdminLayout::render('Sales analytics', $body, 'sales'));
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
