<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Sales\SalesRepository;

/**
 * Tenant-admin sales workflow screen.
 */
final class SalesController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly SalesRepository $sales,
        private readonly CsrfTokenService $csrf,
        private readonly ?AuditLogRepository $auditLog = null,
    ) {}

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $rows = '';
        foreach ($this->sales->orders($tenant) as $order) {
            $id = (int) $order['id'];
            $items = $this->sales->orderItems($id);
            $itemList = '<ul>';
            foreach ($items as $item) {
                $itemList .= '<li>' . $this->e((string) $item['title_snapshot']) . ' × ' . (int) $item['quantity'] . '</li>';
            }
            $itemList .= '</ul>';
            $rows .= '<tr><td><a href="/admin/sales?id=' . $id . '">' . $this->e((string) $order['order_number']) . '</a><br><small>' . $this->e((string) $order['created_at']) . '</small></td><td>' . $itemList . '</td><td>' . $this->e((string) $order['payment_status']) . '</td><td>' . $this->e((string) $order['workflow_status']) . '</td><td>' . $this->money((int) $order['total_cents']) . '</td><td>' . $this->money((int) ($order['commission_cents'] ?? 0)) . '</td><td>' . $this->money((int) ($order['credit_card_fee_cents'] ?? 0)) . '</td><td>' . $this->money((int) ($order['seller_net_cents'] ?? max(0, (int) $order['total_cents'] - (int) ($order['commission_cents'] ?? 0) - (int) ($order['credit_card_fee_cents'] ?? 0)))) . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="8">No sales yet.</td></tr>';
        }

        $detail = '';
        $selectedId = (int) ($_GET['id'] ?? 0);
        if ($selectedId > 0) {
            $order = $this->sales->order($tenant, $selectedId);
            if ($order) {
                $csrf = $this->e($this->csrf->getOrCreate());
                $detail = $this->detailForm($order, $csrf);
            }
        }

        $body = <<<HTML
<p class="admin-muted">Track orders from Stripe checkout through ordered, acknowledged, packed, and shipped.</p>
<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Order</th><th>Items</th><th>Payment</th><th>Workflow</th><th>Total</th><th>Commission</th><th>CC fees</th><th>Seller net</th></tr></thead><tbody>{$rows}</tbody></table></div>
{$detail}
HTML;

        return Response::html(AdminLayout::render('Sales', $body, 'sales'));
    }

    public function update(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin']) || !$this->csrf->verify((string) ($_POST['csrf_token'] ?? ''))) {
            return new Response('', 303, ['Location' => '/admin/sales?notice=security']);
        }

        $orderId = (int) ($_POST['order_id'] ?? 0);
        $status = (string) ($_POST['workflow_status'] ?? 'ordered');
        $shipping = [
            'carrier' => trim((string) ($_POST['shipping_carrier'] ?? '')) ?: null,
            'tracking' => trim((string) ($_POST['shipping_tracking_number'] ?? '')) ?: null,
            'url' => trim((string) ($_POST['shipping_tracking_url'] ?? '')) ?: null,
        ];
        $this->sales->updateWorkflow($tenant, $orderId, $status, $shipping);
        $this->auditLog?->record('tenant.sales.workflow_updated', $tenant->tenantId, isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null, 'sales_order', (string) $orderId, ['workflow_status' => $status], $request->server('REMOTE_ADDR'));

        return new Response('', 303, ['Location' => '/admin/sales?id=' . $orderId . '&notice=saved']);
    }

    private function detailForm(array $order, string $csrf): string
    {
        $id = (int) $order['id'];
        $status = (string) $order['workflow_status'];
        $option = fn (string $value, string $label): string => '<option value="' . $this->e($value) . '"' . ($status === $value ? ' selected' : '') . '>' . $this->e($label) . '</option>';
        return '<section class="admin-panel"><h2>Update ' . $this->e((string) $order['order_number']) . '</h2><form method="post" action="/admin/sales/update"><input type="hidden" name="csrf_token" value="' . $csrf . '"><input type="hidden" name="order_id" value="' . $id . '"><label>Workflow status<select name="workflow_status">' . $option('ordered', 'Ordered') . $option('acknowledged', 'Acknowledged') . $option('packed', 'Packed') . $option('shipped', 'Shipped') . '</select></label><label>Shipping carrier<input name="shipping_carrier" value="' . $this->e((string) ($order['shipping_carrier'] ?? '')) . '"></label><label>Tracking number<input name="shipping_tracking_number" value="' . $this->e((string) ($order['shipping_tracking_number'] ?? '')) . '"></label><label>Tracking URL<input name="shipping_tracking_url" value="' . $this->e((string) ($order['shipping_tracking_url'] ?? '')) . '"></label><button type="submit">Save sales workflow</button></form></section>';
    }

    private function money(int $cents): string { return '$' . number_format($cents / 100, 2); }
    private function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
}

// End of file.
