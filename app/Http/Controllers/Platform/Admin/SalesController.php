<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Tenant\Sales\SalesRepository;

/**
 * Platform-admin sales visibility and aggregate analytics.
 */
final class SalesController
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

        $orders = $this->sales->platformOrders();
        $total = 0;
        $commission = 0;
        $rows = '';
        foreach ($orders as $order) {
            $total += (int) $order['total_cents'];
            $commission += (int) $order['commission_cents'];
            $tenantUrl = 'https://' . $this->e((string) $order['tenant_slug']) . '.artsfol.io/';
            $rows .= '<tr><td>' . $this->e((string) $order['order_number']) . '</td><td><a href="' . $tenantUrl . '">' . $this->e((string) $order['tenant_name']) . '</a></td><td>' . $this->e((string) $order['payment_status']) . '</td><td>' . $this->e((string) $order['workflow_status']) . '</td><td>' . $this->money((int) $order['total_cents']) . '</td><td>' . $this->money((int) $order['commission_cents']) . '</td><td>' . $this->e((string) $order['created_at']) . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="7">No sales yet.</td></tr>';
        }

        $body = '<section class="admin-billing-summary"><div class="admin-panel"><p class="admin-muted">Gross sales</p><h2>' . $this->money($total) . '</h2></div><div class="admin-panel"><p class="admin-muted">Platform commission</p><h2>' . $this->money($commission) . '</h2></div></section><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Order</th><th>Tenant</th><th>Payment</th><th>Workflow</th><th>Total</th><th>Commission</th><th>Date</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';

        return Response::html(AdminLayout::render('Sales', $body, 'sales'));
    }

    private function money(int $cents): string { return '$' . number_format($cents / 100, 2); }
    private function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
}

// End of file.
