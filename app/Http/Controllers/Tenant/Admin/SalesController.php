<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Settings\PlatformSettingsRepository;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Sales\SalesRepository;
use App\Tenant\Sales\StripeCheckoutService;
use Throwable;

/**
 * Tenant-admin sales workflow screen.
 *
 * This controller is deliberately explicit about Stripe refunds: clicking the
 * refund button sends a live Stripe Refund request immediately, then records the
 * returned refund id locally for review and audit history.
 */
final class SalesController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly SalesRepository $sales,
        private readonly CsrfTokenService $csrf,
        private readonly ?AuditLogRepository $auditLog = null,
        private readonly ?PlatformSettingsRepository $platformSettings = null,
    ) {}

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $notice = $this->notice((string) ($_GET['notice'] ?? ''));
        $rows = '';
        foreach ($this->sales->orders($tenant) as $order) {
            $id = (int) $order['id'];
            $items = $this->sales->orderItems($id);
            $itemList = '<ul>';
            foreach ($items as $item) {
                $variant = $this->itemVariantSummary($item);
                $itemList .= '<li>' . $this->e((string) $item['title_snapshot']) . $variant . ' × ' . (int) $item['quantity'] . '</li>';
            }
            $itemList .= '</ul>';
            $paymentStatus = (string) $order['payment_status'];
            $workflowStatus = (string) $order['workflow_status'];
            $stripeRef = trim((string) ($order['stripe_payment_intent_id'] ?? ''));
            $stripeLine = $stripeRef !== '' ? '<br><small>PI: ' . $this->e($stripeRef) . '</small>' : '';
            $rows .= '<tr><td><a href="/admin/sales?id=' . $id . '">' . $this->e((string) $order['order_number']) . '</a><br><small>' . $this->e((string) $order['created_at']) . '</small></td><td>' . $itemList . '</td><td>' . $this->statusBadge($paymentStatus) . $stripeLine . '</td><td>' . $this->statusBadge($workflowStatus) . '</td><td>' . $this->money((int) $order['total_cents']) . '</td><td>' . $this->money((int) ($order['commission_cents'] ?? 0)) . '</td><td>' . $this->money((int) ($order['credit_card_fee_cents'] ?? 0)) . '</td><td>' . $this->money((int) ($order['seller_net_cents'] ?? max(0, (int) $order['total_cents'] - (int) ($order['commission_cents'] ?? 0) - (int) ($order['credit_card_fee_cents'] ?? 0)))) . '</td></tr>';
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
{$notice}
<p class="admin-muted">Track orders from Stripe checkout through ordered, acknowledged, packed, shipped, and refunded. Open an order to review Stripe references, item detail, refund history, and refund actions.</p>
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

    public function refund(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin']) || !$this->csrf->verify((string) ($_POST['csrf_token'] ?? ''))) {
            return new Response('', 303, ['Location' => '/admin/sales?notice=security']);
        }

        $orderId = (int) ($_POST['order_id'] ?? 0);
        $order = $this->sales->order($tenant, $orderId);
        if (!$order) {
            return new Response('', 303, ['Location' => '/admin/sales?notice=missing']);
        }

        $remainingCents = max(0, (int) $order['total_cents'] - $this->sales->orderRefundTotal($orderId));
        $scope = (string) ($_POST['refund_scope'] ?? 'full');
        $amountCents = $scope === 'custom' ? $this->dollarsToCents((string) ($_POST['refund_amount'] ?? '0')) : $remainingCents;
        $reason = $this->stripeReason((string) ($_POST['refund_reason'] ?? 'requested_by_customer'));
        $restockInventory = isset($_POST['restock_inventory']) && $scope !== 'custom' && $amountCents === $remainingCents;
        $paymentIntentId = trim((string) ($order['stripe_payment_intent_id'] ?? ''));

        if (!in_array((string) $order['payment_status'], ['paid', 'complete', 'succeeded', 'partially_refunded'], true)) {
            return $this->errorPage('Refund unavailable', 'Only paid orders can be refunded from ArtsFolio.', $orderId);
        }
        if ($paymentIntentId === '') {
            return $this->errorPage('Refund unavailable', 'This order does not have a Stripe PaymentIntent id recorded.', $orderId);
        }
        if ($amountCents <= 0 || $amountCents > $remainingCents) {
            return $this->errorPage('Refund unavailable', 'The refund amount must be greater than zero and no more than the remaining captured amount.', $orderId);
        }

        try {
            $secretKey = (string) ($this->platformSettings?->get('stripe_secret_key', '') ?? '');
            $refund = (new StripeCheckoutService())->refundPaymentIntent($secretKey, $paymentIntentId, $amountCents, $reason);
            $this->sales->recordStripeRefund(
                $tenant,
                $orderId,
                (string) $refund['id'],
                $paymentIntentId,
                max(0, (int) ($refund['amount'] ?? $amountCents)),
                $reason,
                (string) ($refund['status'] ?? 'unknown'),
                $refund,
                isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null,
                $restockInventory,
            );
            $this->auditLog?->record('tenant.sales.refund_created', $tenant->tenantId, isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null, 'sales_order', (string) $orderId, ['stripe_refund_id' => (string) $refund['id'], 'amount_cents' => $amountCents, 'reason' => $reason, 'restock_inventory' => $restockInventory], $request->server('REMOTE_ADDR'));
        } catch (Throwable $e) {
            return $this->errorPage('Stripe refund failed', $e->getMessage(), $orderId);
        }

        return new Response('', 303, ['Location' => '/admin/sales?id=' . $orderId . '&notice=refund_sent']);
    }

    private function detailForm(array $order, string $csrf): string
    {
        $id = (int) $order['id'];
        $status = (string) $order['workflow_status'];
        $option = fn (string $value, string $label): string => '<option value="' . $this->e($value) . '"' . ($status === $value ? ' selected' : '') . '>' . $this->e($label) . '</option>';
        $itemsHtml = $this->orderItemsHtml($id);
        $refundsHtml = $this->refundsHtml($id);
        $refundForm = $this->refundForm($order, $csrf);
        $stripe = $this->stripeReviewHtml($order);
        $customer = $this->customerHtml($order);
        $notes = trim((string) ($order['notes'] ?? '')) !== '' ? '<h3>Notes</h3><pre class="admin-code-block">' . $this->e((string) $order['notes']) . '</pre>' : '';

        return '<section class="admin-panel"><h2>Review ' . $this->e((string) $order['order_number']) . '</h2>'
            . '<div class="admin-form-grid three"><div><h3>Totals</h3><p><strong>Subtotal:</strong> ' . $this->money((int) $order['subtotal_cents']) . '<br><strong>Shipping:</strong> ' . $this->money((int) ($order['shipping_cents'] ?? 0)) . '<br><strong>Total:</strong> ' . $this->money((int) $order['total_cents']) . '<br><strong>Payment:</strong> ' . $this->statusBadge((string) $order['payment_status']) . '<br><strong>Workflow:</strong> ' . $this->statusBadge((string) $order['workflow_status']) . '</p></div>' . $customer . $stripe . '</div>'
            . $itemsHtml
            . $refundsHtml
            . $refundForm
            . '<h3>Sales workflow</h3><form method="post" action="/admin/sales/update"><input type="hidden" name="csrf_token" value="' . $csrf . '"><input type="hidden" name="order_id" value="' . $id . '"><label>Workflow status<select name="workflow_status">' . $option('ordered', 'Ordered') . $option('acknowledged', 'Acknowledged') . $option('packed', 'Packed') . $option('shipped', 'Shipped') . $option('refunded', 'Refunded') . '</select></label><label>Shipping carrier<input name="shipping_carrier" value="' . $this->e((string) ($order['shipping_carrier'] ?? '')) . '"></label><label>Tracking number<input name="shipping_tracking_number" value="' . $this->e((string) ($order['shipping_tracking_number'] ?? '')) . '"></label><label>Tracking URL<input name="shipping_tracking_url" value="' . $this->e((string) ($order['shipping_tracking_url'] ?? '')) . '"></label><button type="submit">Save sales workflow</button></form>'
            . $notes
            . '</section>';
    }

    private function orderItemsHtml(int $orderId): string
    {
        $rows = '';
        foreach ($this->sales->orderItems($orderId) as $item) {
            $rows .= '<tr><td>' . $this->e((string) $item['title_snapshot']) . $this->itemVariantSummary($item) . '</td><td>' . (int) $item['quantity'] . '</td><td>' . $this->money((int) $item['unit_price_cents']) . '</td><td>' . $this->money((int) ($item['shipping_total_cents'] ?? $item['shipping_price_cents'] ?? 0)) . '</td><td>' . $this->money((int) ($item['line_total_cents'] ?? 0) + (int) ($item['shipping_total_cents'] ?? 0)) . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="5">No order items recorded.</td></tr>';
        }

        return '<h3>Order items</h3><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Item</th><th>Qty</th><th>Unit</th><th>Shipping</th><th>Total</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    private function refundsHtml(int $orderId): string
    {
        $refunds = $this->sales->orderRefunds($orderId);
        if ($refunds === []) {
            return '<h3>Refund history</h3><p class="admin-muted">No refunds recorded for this order.</p>';
        }
        $rows = '';
        foreach ($refunds as $refund) {
            $rows .= '<tr><td>' . $this->e((string) $refund['created_at']) . '</td><td>' . $this->money((int) $refund['amount_cents']) . '</td><td>' . $this->e((string) $refund['reason']) . '</td><td>' . $this->statusBadge((string) $refund['status']) . '</td><td><code>' . $this->e((string) $refund['stripe_refund_id']) . '</code></td></tr>';
        }

        return '<h3>Refund history</h3><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Amount</th><th>Reason</th><th>Status</th><th>Stripe refund</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    private function refundForm(array $order, string $csrf): string
    {
        $orderId = (int) $order['id'];
        $refunded = $this->sales->orderRefundTotal($orderId);
        $remaining = max(0, (int) $order['total_cents'] - $refunded);
        $paymentIntent = trim((string) ($order['stripe_payment_intent_id'] ?? ''));
        $paid = in_array((string) $order['payment_status'], ['paid', 'complete', 'succeeded', 'partially_refunded'], true);
        if (!$paid || $paymentIntent === '' || $remaining <= 0) {
            return '<h3>Refund from Stripe</h3><p class="admin-muted">Refund is unavailable because the order is not paid, lacks a Stripe PaymentIntent, or has already been fully refunded.</p>';
        }

        return '<h3>Refund from Stripe</h3><form method="post" action="/admin/sales/refund" onsubmit="return confirm(\'This will create a live Stripe refund immediately. Continue?\');"><input type="hidden" name="csrf_token" value="' . $csrf . '"><input type="hidden" name="order_id" value="' . $orderId . '"><p class="admin-muted">Remaining refundable amount: <strong>' . $this->money($remaining) . '</strong>. Use Stripe Dashboard for unusual disputes or chargeback workflows.</p><label>Refund amount<select name="refund_scope"><option value="full">Full remaining amount (' . $this->money($remaining) . ')</option><option value="custom">Custom amount below</option></select></label><label>Custom refund amount in dollars<input name="refund_amount" inputmode="decimal" placeholder="0.00"></label><label>Stripe reason<select name="refund_reason"><option value="requested_by_customer">Requested by customer</option><option value="duplicate">Duplicate charge/order</option><option value="fraudulent">Fraudulent</option></select></label><label><input type="checkbox" name="restock_inventory" value="1" checked> Return completed order inventory to available stock for a full refund</label><button type="submit">Create Stripe refund</button></form>';
    }

    private function stripeReviewHtml(array $order): string
    {
        $session = trim((string) ($order['stripe_checkout_session_id'] ?? ''));
        $intent = trim((string) ($order['stripe_payment_intent_id'] ?? ''));

        return '<div><h3>Stripe</h3><p><strong>Checkout Session:</strong><br><code>' . $this->e($session !== '' ? $session : 'not recorded') . '</code><br><strong>PaymentIntent:</strong><br><code>' . $this->e($intent !== '' ? $intent : 'not recorded') . '</code></p></div>';
    }

    private function customerHtml(array $order): string
    {
        $name = trim((string) ($order['customer_name'] ?? ''));
        $email = trim((string) ($order['customer_email'] ?? ''));
        $shipping = trim((string) ($order['shipping_address_json'] ?? ''));
        $shippingHtml = $shipping !== '' ? '<br><strong>Shipping:</strong><br><code>' . $this->e($shipping) . '</code>' : '';

        return '<div><h3>Customer</h3><p><strong>Name:</strong> ' . $this->e($name !== '' ? $name : 'not recorded') . '<br><strong>Email:</strong> ' . $this->e($email !== '' ? $email : 'not recorded') . $shippingHtml . '</p></div>';
    }

    private function itemVariantSummary(array $item): string
    {
        $parts = [];
        foreach (['variant_label_snapshot', 'size_value_snapshot', 'gender_value_snapshot'] as $key) {
            $value = trim((string) ($item[$key] ?? ''));
            if ($value !== '' && $value !== 'Default' && $value !== 'not_applicable') {
                $parts[] = $this->e($value);
            }
        }

        return $parts === [] ? '' : '<br><small>' . implode(' · ', array_unique($parts)) . '</small>';
    }

    private function dollarsToCents(string $value): int
    {
        $normalized = preg_replace('/[^0-9.]/', '', $value) ?? '';
        if ($normalized === '') {
            return 0;
        }

        return max(0, (int) round(((float) $normalized) * 100));
    }

    private function stripeReason(string $reason): string
    {
        return in_array($reason, ['duplicate', 'fraudulent', 'requested_by_customer'], true) ? $reason : 'requested_by_customer';
    }

    private function errorPage(string $title, string $message, int $orderId): Response
    {
        return Response::html(AdminLayout::render($title, '<section class="admin-panel"><h2>' . $this->e($title) . '</h2><p>' . $this->e($message) . '</p><p><a href="/admin/sales?id=' . $orderId . '">Return to order</a></p></section>', 'sales'), 422);
    }

    private function notice(string $notice): string
    {
        return match ($notice) {
            'refund_sent' => '<div class="admin-notice success">Stripe refund created and recorded.</div>',
            'saved' => '<div class="admin-notice success">Sales workflow saved.</div>',
            'security' => '<div class="admin-notice error">Security check failed. Please try again.</div>',
            default => '',
        };
    }

    private function statusBadge(string $status): string
    {
        return '<span class="admin-status-badge">' . $this->e($status) . '</span>';
    }

    private function money(int $cents): string { return '$' . number_format($cents / 100, 2); }
    private function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
}

// End of file.
