<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Email\EmailOutboxRepository;
use App\Platform\Settings\PlatformSettingsRepository;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Sales\SalesRepository;
use App\Tenant\Sales\StripeCheckoutService;
use Throwable;

/**
 * Tenant-admin sales workflow screen.
 *
 * Paid orders are shown by default so abandoned, unpaid, and zero-sale checkout
 * rows do not muddy the sales desk. The optional include_no_sales filter keeps
 * support visibility available when someone needs to investigate a checkout.
 */
final class SalesController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly SalesRepository $sales,
        private readonly CsrfTokenService $csrf,
        private readonly ?AuditLogRepository $auditLog = null,
        private readonly ?PlatformSettingsRepository $platformSettings = null,
        private readonly ?EmailOutboxRepository $outbox = null,
    ) {}

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $includeNoSales = $this->includeNoSales();
        $notice = $this->notice((string) ($_GET['notice'] ?? ''));
        $filterHtml = $this->filterHtml($includeNoSales);
        $rows = '';
        foreach ($this->sales->orders($tenant, 100, $includeNoSales) as $order) {
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
            $shippingEmail = trim((string) ($order['shipping_email_sent_at'] ?? ''));
            $shippingLine = $shippingEmail !== '' ? '<br><small>Buyer emailed: ' . $this->e($shippingEmail) . '</small>' : '';
            $rows .= '<tr><td><a href="/admin/sales/order?id=' . $id . $this->includeNoSalesQuery($includeNoSales) . '">' . $this->e((string) $order['order_number']) . '</a><br><small>' . $this->e((string) $order['created_at']) . '</small></td><td>' . $itemList . '</td><td>' . $this->statusBadge($paymentStatus) . $stripeLine . '</td><td>' . $this->statusBadge($workflowStatus) . $shippingLine . '</td><td>' . $this->money((int) $order['total_cents']) . '</td><td>' . $this->money((int) ($order['commission_cents'] ?? 0)) . '</td><td>' . $this->money((int) ($order['credit_card_fee_cents'] ?? 0)) . '</td><td>' . $this->money((int) ($order['seller_net_cents'] ?? max(0, (int) $order['total_cents'] - (int) ($order['commission_cents'] ?? 0) - (int) ($order['credit_card_fee_cents'] ?? 0)))) . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="8">No paid sales yet. Use “Show no-sale checkout rows” to inspect abandoned or unpaid checkout records.</td></tr>';
        }
        $body = <<<HTML
{$notice}
<p class="admin-muted">Track paid orders from Stripe checkout through ordered, acknowledged, packed, shipped, and refunded. Open an order to review Stripe references, item detail, refund history, refund actions, and buyer shipping email controls.</p>
{$filterHtml}
<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Order</th><th>Items</th><th>Payment</th><th>Workflow</th><th>Total</th><th>Commission</th><th>CC fees</th><th>Seller net</th></tr></thead><tbody>{$rows}</tbody></table></div>
HTML;

        return Response::html(AdminLayout::render('Sales', $body, 'sales'));
    }


    public function show(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $orderId = (int) ($_GET['id'] ?? $_GET['order_id'] ?? 0);
        if ($orderId <= 0) {
            return new Response('', 303, ['Location' => '/admin/sales?notice=missing']);
        }

        $order = $this->sales->order($tenant, $orderId);
        if (!$order) {
            return new Response('', 303, ['Location' => '/admin/sales?notice=missing']);
        }

        $includeNoSales = isset($_GET['include_no_sales']);
        $notice = $this->notice((string) ($_GET['notice'] ?? ''));
        $csrf = $this->e($this->csrf->getOrCreate());
        $body = $notice
            . '<p><a class="admin-link" href="/admin/sales' . $this->includeNoSalesQuery($includeNoSales) . '">← Back to sales</a></p>'
            . $this->detailForm($order, $csrf, $includeNoSales);

        return Response::html(AdminLayout::render('Review ' . (string) $order['order_number'], $body, 'sales'));
    }

    public function update(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin']) || !$this->csrf->verify((string) ($_POST['csrf_token'] ?? ''))) {
            return new Response('', 303, ['Location' => '/admin/sales?notice=security']);
        }

        $orderId = (int) ($_POST['order_id'] ?? 0);
        $includeNoSales = isset($_POST['include_no_sales']);
        $order = $this->sales->order($tenant, $orderId);
        if (!$order) {
            return new Response('', 303, ['Location' => '/admin/sales?notice=missing' . $this->includeNoSalesQuery($includeNoSales)]);
        }

        $status = (string) ($_POST['workflow_status'] ?? 'ordered');
        $shipping = [
            'carrier' => trim((string) ($_POST['shipping_carrier'] ?? '')) ?: null,
            'tracking' => trim((string) ($_POST['shipping_tracking_number'] ?? '')) ?: null,
            'url' => trim((string) ($_POST['shipping_tracking_url'] ?? '')) ?: null,
        ];
        $this->sales->updateWorkflow($tenant, $orderId, $status, $shipping);

        $notice = 'saved';
        $emailOutboxId = null;
        if (isset($_POST['send_shipping_email'])) {
            $emailOutboxId = $this->queueShippingNotification($tenant, $order + ['workflow_status' => $status], $status, $shipping);
            if ($emailOutboxId !== null) {
                $this->sales->markShippingEmailQueued($tenant, $orderId, $emailOutboxId);
                $notice = 'saved_shipping_email';
            } else {
                $notice = 'saved_shipping_email_unavailable';
            }
        }

        $this->auditLog?->record(
            'tenant.sales.workflow_updated',
            $tenant->tenantId,
            isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null,
            'sales_order',
            (string) $orderId,
            ['workflow_status' => $status, 'shipping_email_outbox_id' => $emailOutboxId],
            $request->server('REMOTE_ADDR'),
        );

        return new Response('', 303, ['Location' => '/admin/sales/order?id=' . $orderId . '&notice=' . $notice . $this->includeNoSalesQuery($includeNoSales)]);
    }

    public function refundEntry(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $orderId = (int) ($_GET['order_id'] ?? $_GET['id'] ?? 0);
        $includeNoSales = isset($_GET['include_no_sales']);
        $target = '/admin/sales?notice=refund_direct_link' . $this->includeNoSalesQuery($includeNoSales);
        if ($orderId > 0) {
            $target = '/admin/sales/order?id=' . $orderId . '&notice=refund_direct_link' . $this->includeNoSalesQuery($includeNoSales);
        }

        return new Response('', 303, ['Location' => $target]);
    }

    /**
     * Redirect direct browser loads of the refund action back to the review UI.
     * Refund creation must remain POST-only because it talks to live Stripe.
     */
    public function refundGet(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $orderId = (int) ($_GET['order_id'] ?? $_GET['id'] ?? 0);
        $location = $orderId > 0
            ? '/admin/sales/order?id=' . $orderId . '&notice=refund_direct'
            : '/admin/sales?notice=refund_direct';

        return new Response('', 303, ['Location' => $location]);
    }

    public function refund(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        $orderId = (int) ($_POST['order_id'] ?? 0);

        try {
            if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin']) || !$this->csrf->verify((string) ($_POST['csrf_token'] ?? ''))) {
                return new Response('', 303, ['Location' => '/admin/sales?notice=security']);
            }

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
                return $this->refundProblemPage('Refund unavailable', 'Only paid orders can be refunded from ArtsFolio.', $orderId);
            }
            if ($paymentIntentId === '') {
                return $this->refundProblemPage('Refund unavailable', 'This order does not have a Stripe PaymentIntent id recorded.', $orderId);
            }
            if ($amountCents <= 0 || $amountCents > $remainingCents) {
                return $this->refundProblemPage('Refund unavailable', 'The refund amount must be greater than zero and no more than the remaining captured amount.', $orderId);
            }

            $secretKey = (string) ($this->platformSettings?->get('stripe_secret_key', '') ?? '');
            $idempotencyKey = $this->refundIdempotencyKey($tenant->tenantId, $orderId, $paymentIntentId, $amountCents, $reason);
            $refund = (new StripeCheckoutService())->refundPaymentIntent($secretKey, $paymentIntentId, $amountCents, $reason, $idempotencyKey);
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
            $this->auditLog?->record('tenant.sales.refund_created', $tenant->tenantId, isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null, 'sales_order', (string) $orderId, ['stripe_refund_id' => (string) $refund['id'], 'amount_cents' => $amountCents, 'reason' => $reason, 'restock_inventory' => $restockInventory, 'stripe_idempotency_key' => $idempotencyKey], $request->server('REMOTE_ADDR'));

            return new Response('', 303, ['Location' => '/admin/sales/order?id=' . $orderId . '&notice=refund_sent']);
        } catch (Throwable $e) {
            error_log('ArtsFolio sales refund failed: order_id=' . $orderId . ' error=' . $e->getMessage());
            return $this->refundProblemPage('Stripe refund failed', $e->getMessage(), $orderId);
        }
    }

    private function detailForm(array $order, string $csrf, bool $includeNoSales): string
    {
        $id = (int) $order['id'];
        $status = (string) $order['workflow_status'];
        $option = fn (string $value, string $label): string => '<option value="' . $this->e($value) . '"' . ($status === $value ? ' selected' : '') . '>' . $this->e($label) . '</option>';
        $itemsHtml = $this->orderItemsHtml($id);
        $refundsHtml = $this->refundsHtml($id);
        $refundForm = $this->refundForm($order, $csrf, $includeNoSales);
        $stripe = $this->stripeReviewHtml($order);
        $customer = $this->customerHtml($order);
        $shippingEmailHtml = $this->shippingEmailControls($order);
        $includeHidden = $includeNoSales ? '<input type="hidden" name="include_no_sales" value="1">' : '';
        $notes = trim((string) ($order['notes'] ?? '')) !== '' ? '<h3>Notes</h3><pre class="admin-code-block">' . $this->e((string) $order['notes']) . '</pre>' : '';

        return '<section class="admin-panel"><h2>Review ' . $this->e((string) $order['order_number']) . '</h2>'
            . '<div class="admin-form-grid three"><div><h3>Totals</h3><p><strong>Subtotal:</strong> ' . $this->money((int) $order['subtotal_cents']) . '<br><strong>Shipping:</strong> ' . $this->money((int) ($order['shipping_cents'] ?? 0)) . '<br><strong>Total:</strong> ' . $this->money((int) $order['total_cents']) . '<br><strong>Payment:</strong> ' . $this->statusBadge((string) $order['payment_status']) . '<br><strong>Workflow:</strong> ' . $this->statusBadge((string) $order['workflow_status']) . '</p></div>' . $customer . $stripe . '</div>'
            . $itemsHtml
            . $refundsHtml
            . $refundForm
            . '<h3>Sales workflow</h3><form method="post" action="/admin/sales/update"><input type="hidden" name="csrf_token" value="' . $csrf . '"><input type="hidden" name="order_id" value="' . $id . '">' . $includeHidden . '<label>Workflow status<select name="workflow_status">' . $option('ordered', 'Ordered') . $option('acknowledged', 'Acknowledged') . $option('packed', 'Packed') . $option('shipped', 'Shipped') . $option('refunded', 'Refunded') . '</select></label><label>Shipping carrier<input name="shipping_carrier" value="' . $this->e((string) ($order['shipping_carrier'] ?? '')) . '"></label><label>Tracking number<input name="shipping_tracking_number" value="' . $this->e((string) ($order['shipping_tracking_number'] ?? '')) . '"></label><label>Tracking URL<input name="shipping_tracking_url" value="' . $this->e((string) ($order['shipping_tracking_url'] ?? '')) . '"></label>' . $shippingEmailHtml . '<button type="submit">Save sales workflow</button></form>'
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

    private function refundForm(array $order, string $csrf, bool $includeNoSales): string
    {
        $orderId = (int) $order['id'];
        $refunded = $this->sales->orderRefundTotal($orderId);
        $remaining = max(0, (int) $order['total_cents'] - $refunded);
        $paymentIntent = trim((string) ($order['stripe_payment_intent_id'] ?? ''));
        $paid = in_array((string) $order['payment_status'], ['paid', 'complete', 'succeeded', 'partially_refunded'], true);
        if (!$paid || $paymentIntent === '' || $remaining <= 0) {
            return '<h3>Refund from Stripe</h3><p class="admin-muted">Refund is unavailable because the order is not paid, lacks a Stripe PaymentIntent, or has already been fully refunded.</p>';
        }

        $includeHidden = $includeNoSales ? '<input type="hidden" name="include_no_sales" value="1">' : '';
        return '<h3>Refund from Stripe</h3><form method="post" action="/admin/sales/refund" onsubmit="return confirm(\'This will create a live Stripe refund immediately. Continue?\');"><input type="hidden" name="csrf_token" value="' . $csrf . '"><input type="hidden" name="order_id" value="' . $orderId . '">' . $includeHidden . '<p class="admin-muted">Remaining refundable amount: <strong>' . $this->money($remaining) . '</strong>. Use Stripe Dashboard for unusual disputes or chargeback workflows.</p><label>Refund amount<select name="refund_scope"><option value="full">Full remaining amount (' . $this->money($remaining) . ')</option><option value="custom">Custom amount below</option></select></label><label>Custom refund amount in dollars<input name="refund_amount" inputmode="decimal" placeholder="0.00"></label><label>Stripe reason<select name="refund_reason"><option value="requested_by_customer">Requested by customer</option><option value="duplicate">Duplicate charge/order</option><option value="fraudulent">Fraudulent</option></select></label><label><input type="checkbox" name="restock_inventory" value="1" checked> Return completed order inventory to available stock for a full refund</label><button type="submit">Create Stripe refund</button></form>';
    }

    private function shippingEmailControls(array $order): string
    {
        $email = trim((string) ($order['customer_email'] ?? ''));
        $sentAt = trim((string) ($order['shipping_email_sent_at'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '<p class="admin-muted">Buyer shipping email unavailable because this order has no valid buyer email.</p>';
        }

        $sent = $sentAt !== '' ? '<br><small>Last queued: ' . $this->e($sentAt) . '</small>' : '';
        return '<label><input type="checkbox" name="send_shipping_email" value="1"> Email shipping details to ' . $this->e($email) . '</label><p class="admin-muted">The email is queued only when the workflow status is Shipped. It includes the carrier, tracking number, tracking URL, order number, and item summary.' . $sent . '</p>';
    }

    private function queueShippingNotification(TenantContext $tenant, array $order, string $status, array $shipping): ?int
    {
        if ($this->outbox === null || $status !== 'shipped') {
            return null;
        }

        $recipientEmail = strtolower(trim((string) ($order['customer_email'] ?? '')));
        if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $recipientName = trim((string) ($order['customer_name'] ?? '')) ?: null;
        $orderNumber = (string) ($order['order_number'] ?? ('#' . (int) ($order['id'] ?? 0)));
        $carrier = trim((string) ($shipping['carrier'] ?? '')) ?: 'Carrier not provided';
        $tracking = trim((string) ($shipping['tracking'] ?? '')) ?: 'Tracking number not provided';
        $trackingUrl = trim((string) ($shipping['url'] ?? '')) ?: 'Tracking URL not provided';
        $itemLines = [];
        foreach ($this->sales->orderItems((int) $order['id']) as $item) {
            $itemLines[] = '- ' . (int) $item['quantity'] . ' × ' . (string) $item['title_snapshot'] . $this->plainVariantSummary($item);
        }
        if ($itemLines === []) {
            $itemLines[] = '- Order item details are not available in ArtsFolio.';
        }

        $subject = 'Shipping details for ArtsFolio order ' . $orderNumber;
        $body = implode("\n", [
            $recipientName ? 'Hello ' . $recipientName . ',' : 'Hello,',
            '',
            'Your ArtsFolio order ' . $orderNumber . ' from ' . $tenant->name . ' has shipped.',
            '',
            'Shipping details:',
            'Carrier: ' . $carrier,
            'Tracking number: ' . $tracking,
            'Tracking URL: ' . $trackingUrl,
            '',
            'Order items:',
            implode("\n", $itemLines),
            '',
            'Questions? Reply to this email and the artist can help.',
            '',
            'ArtsFolio',
        ]);

        return $this->outbox->queue(
            recipientEmail: $recipientEmail,
            subject: $subject,
            bodyText: $body,
            recipientName: $recipientName,
            tenantId: $tenant->tenantId,
            templateKey: 'sales.shipping_notification',
        );
    }

    private function plainVariantSummary(array $item): string
    {
        $parts = [];
        foreach (['variant_label_snapshot', 'size_value_snapshot', 'gender_value_snapshot'] as $key) {
            $value = trim((string) ($item[$key] ?? ''));
            if ($value !== '' && $value !== 'Default' && $value !== 'not_applicable') {
                $parts[] = $value;
            }
        }

        return $parts === [] ? '' : ' (' . implode(' / ', array_unique($parts)) . ')';
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
        $shippingHtml = $this->shippingAddressHtml($order);

        return '<div><h3>Customer</h3><p><strong>Name:</strong> '
            . $this->e($name !== '' ? $name : 'not recorded')
            . '<br><strong>Email:</strong> '
            . $this->e($email !== '' ? $email : 'not recorded')
            . $shippingHtml
            . '</p></div>';
    }


    /**
     * Renders the buyer shipping address without exposing raw Stripe JSON.
     *
     * Stripe Checkout stores shipping details as a nested JSON object. Older
     * order screens printed that blob directly, which was hard to read and made
     * the dedicated review page disagree with the legacy inline panel. This
     * method keeps all order pages on one normalized display path.
     */
    private function shippingAddressHtml(array $order): string
    {
        $address = $this->normalizedShippingAddress($order);
        if ($address === []) {
            return '<br><strong>Shipping address:</strong><br><span class="admin-muted">No shipping address recorded.</span>';
        }

        if (isset($address['raw'])) {
            return '<br><strong>Shipping address:</strong><br><div class="admin-shipping-address">'
                . nl2br($this->e((string) $address['raw']), false)
                . '</div>';
        }

        $lines = [];
        foreach (['name', 'line1', 'line2'] as $key) {
            $value = trim((string) ($address[$key] ?? ''));
            if ($value !== '') {
                $lines[] = $value;
            }
        }

        $city = trim((string) ($address['city'] ?? ''));
        $state = trim((string) ($address['state'] ?? ''));
        $postal = trim((string) ($address['postal_code'] ?? ''));
        $localityParts = array_values(array_filter([$city, $state, $postal], static fn (string $part): bool => $part !== ''));
        if ($localityParts !== []) {
            $lines[] = implode(' ', $localityParts);
        }

        $country = trim((string) ($address['country'] ?? ''));
        if ($country !== '') {
            $lines[] = $country;
        }

        $phone = trim((string) ($address['phone'] ?? ''));
        if ($phone !== '') {
            $lines[] = 'Phone: ' . $phone;
        }

        if ($lines === []) {
            return '<br><strong>Shipping address:</strong><br><span class="admin-muted">No shipping address recorded.</span>';
        }

        $htmlLines = array_map(fn (string $line): string => $this->e($line), $lines);

        return '<br><strong>Shipping address:</strong><br><address class="admin-shipping-address">'
            . implode('<br>', $htmlLines)
            . '</address>';
    }

    /**
     * Normalizes known Stripe and legacy shipping payload shapes.
     *
     * @return array<string,string>
     */
    private function normalizedShippingAddress(array $order): array
    {
        $raw = null;
        foreach (['shipping_address_json', 'shipping_details_json', 'shipping_address', 'shipping_details'] as $field) {
            if (!array_key_exists($field, $order)) {
                continue;
            }
            if (is_array($order[$field])) {
                $raw = $order[$field];
                break;
            }
            $candidate = trim((string) ($order[$field] ?? ''));
            if ($candidate !== '') {
                $raw = $candidate;
                break;
            }
        }

        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_array($raw)) {
            $data = $raw;
        } else {
            $data = json_decode((string) $raw, true);
            if (is_string($data)) {
                $data = json_decode($data, true);
            }
            if (!is_array($data)) {
                return ['raw' => (string) $raw];
            }
        }

        if (isset($data['shipping']) && is_array($data['shipping'])) {
            $data = $data['shipping'];
        }
        if (isset($data['shipping_details']) && is_array($data['shipping_details'])) {
            $data = $data['shipping_details'];
        }

        $address = isset($data['address']) && is_array($data['address']) ? $data['address'] : $data;
        $normalized = [];

        foreach (['name', 'phone', 'email'] as $key) {
            $value = $this->valueFromFirstKey($data, [$key]);
            if ($value === '' && $address !== $data) {
                $value = $this->valueFromFirstKey($address, [$key]);
            }
            if ($value !== '') {
                $normalized[$key] = $value;
            }
        }

        $fieldMap = [
            'line1' => ['line1', 'address_line1', 'street', 'street1'],
            'line2' => ['line2', 'address_line2', 'street2'],
            'city' => ['city', 'locality'],
            'state' => ['state', 'region', 'province'],
            'postal_code' => ['postal_code', 'postal', 'zip', 'zip_code'],
            'country' => ['country', 'country_code'],
        ];
        foreach ($fieldMap as $target => $keys) {
            $value = $this->valueFromFirstKey($address, $keys);
            if ($value !== '') {
                $normalized[$target] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Returns the first non-empty scalar value from a candidate key list.
     *
     * @param array<string,mixed> $data
     * @param list<string> $keys
     */
    private function valueFromFirstKey(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data) || is_array($data[$key])) {
                continue;
            }
            $value = trim((string) $data[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
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

    private function filterHtml(bool $includeNoSales): string
    {
        $checked = $includeNoSales ? ' checked' : '';
        return '<form class="admin-filter-bar" method="get" action="/admin/sales"><label><input type="checkbox" name="include_no_sales" value="1"' . $checked . '> Show no-sale checkout rows</label><button type="submit">Apply filter</button><a href="/admin/sales">Paid sales only</a></form>';
    }

    private function includeNoSales(): bool
    {
        return isset($_GET['include_no_sales']) && (string) $_GET['include_no_sales'] === '1';
    }

    private function includeNoSalesQuery(bool $includeNoSales): string
    {
        return $includeNoSales ? '&include_no_sales=1' : '';
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

    private function errorPage(string $title, string $message, int $orderId, bool $includeNoSales = false): Response
    {
        return Response::html(AdminLayout::render($title, '<section class="admin-panel"><h2>' . $this->e($title) . '</h2><p>' . $this->e($message) . '</p><p><a href="/admin/sales/order?id=' . $orderId . $this->includeNoSalesQuery($includeNoSales) . '">Return to order</a></p></section>', 'sales'), 422);
    }

    /**
     * Builds a stable Stripe idempotency key so a browser retry cannot create a
     * second refund for the same order/payment/amount/reason combination.
     */
    private function refundIdempotencyKey(int $tenantId, int $orderId, string $paymentIntentId, int $amountCents, string $reason): string
    {
        return 'artsfolio-refund-' . hash('sha256', implode('|', [
            'tenant', (string) $tenantId,
            'order', (string) $orderId,
            'payment_intent', $paymentIntentId,
            'amount', (string) $amountCents,
            'reason', $reason,
        ]));
    }

    /**
     * Renders refund failures as a controlled admin response instead of letting
     * exceptions bubble into the generic 500 page.
     */
    private function refundProblemPage(string $title, string $message, int $orderId): Response
    {
        $returnUrl = $orderId > 0 ? '/admin/sales/order?id=' . $orderId : '/admin/sales';
        $body = '<section class="admin-panel"><h2>' . $this->e($title) . '</h2><p>' . $this->e($message) . '</p><p class="admin-muted">No further refund should be attempted until this message is checked against the Stripe Dashboard and the ArtsFolio refund history.</p><p><a href="' . $this->e($returnUrl) . '">Return to order</a></p></section>';

        return Response::html(AdminLayout::render($title, $body, 'sales'), 422);
    }

    private function notice(string $notice): string
    {
        return match ($notice) {
            'refund_sent' => '<div class="admin-notice success">Stripe refund created and recorded.</div>',
            'saved_shipping_email' => '<div class="admin-notice success">Sales workflow saved and buyer shipping email queued.</div>',
            'saved_shipping_email_unavailable' => '<div class="admin-notice warning">Sales workflow saved. Buyer shipping email was not queued because the order is not shipped, no buyer email exists, or the mail queue is unavailable.</div>',
            'saved' => '<div class="admin-notice success">Sales workflow saved.</div>',
            'security' => '<div class="admin-notice error">Security check failed. Please try again.</div>',
            'refund_direct' => '<div class="admin-notice warning">Open an order and use the refund form. Refunds cannot be created by loading the refund URL directly.</div>',
            'missing' => '<div class="admin-notice error">Order was not found.</div>',
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
