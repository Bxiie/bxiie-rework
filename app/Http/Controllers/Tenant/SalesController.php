<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Request;
use App\Http\Response;
use App\Platform\Settings\PlatformSettingsRepository;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Sales\CartIdentityService;
use App\Tenant\Sales\SalesRepository;
use App\Tenant\Sales\StripeCheckoutService;
use App\Tenant\Settings\TenantSettingsRepository;
use PDO;
use Throwable;

/**
 * Handles tenant-scoped cart and Stripe Checkout flows.
 */
final class SalesController
{
    public function __construct(
        private readonly SalesRepository $sales,
        private readonly TenantSettingsRepository $tenantSettings,
        private readonly PlatformSettingsRepository $platformSettings,
        private readonly CsrfTokenService $csrf,
        private readonly PDO $pdo,
    ) {}

    public function add(Request $request, TenantContext $tenant): Response
    {
        if (!$this->verifyCsrf()) {
            return Response::html('<h1>Security check failed</h1><p>Please return to the artwork page and try again.</p>', 422);
        }
        if (!$this->salesEnabled($tenant)) {
            return Response::html('<h1>Checkout unavailable</h1><p>Online sales are available on paid ArtsFolio plans.</p>', 403);
        }

        $artworkId = (int) ($_POST['artwork_id'] ?? 0);
        $variantId = (int) ($_POST['variant_id'] ?? 0);
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
        $artwork = $this->sales->artworkForPurchase($tenant, $artworkId);
        $config = $this->sales->saleConfigForArtwork($tenant, $artworkId);
        $variant = $variantId > 0 ? $this->sales->variantForPurchase($tenant, $artworkId, $variantId) : null;

        if (!$artwork || (string) ($artwork['sale_status'] ?? '') !== 'for_sale' || !$config || (int) ($config['checkout_enabled'] ?? 0) !== 1) {
            return Response::html('<h1>Artwork unavailable</h1><p>This artwork is not currently available for online purchase.</p>', 422);
        }
        if (!$variant) {
            $variants = $this->sales->variantsForArtwork($tenant, $artworkId, true);
            $variant = $variants[0] ?? null;
        }
        if (!$variant) {
            return Response::html('<h1>Variant unavailable</h1><p>Please choose another option for this artwork.</p>', 422);
        }
        if ((string) ($config['sale_kind'] ?? 'one_off') === 'one_off') {
            $quantity = 1;
        }

        $available = max(0, (int) ($variant['available_quantity'] ?? 0));
        if ($quantity > $available) {
            return Response::html('<h1>Quantity unavailable</h1><p>Another buyer may currently have this option reserved in checkout. Please try again shortly.</p>', 409);
        }

        $priceCents = (int) ($variant['price_cents'] ?? 0) > 0 ? (int) $variant['price_cents'] : (int) ($config['base_price_cents'] ?? 0);
        if ($priceCents <= 0) {
            return Response::html('<h1>Price unavailable</h1><p>This artwork needs a numeric price before checkout can be used.</p>', 422);
        }

        $shipping = $this->shippingForVariant($config, $variant);
        $identity = new CartIdentityService($this->pdo);
        $resolved = $identity->resolveCartForRequest($tenant, $request, true);
        $cart = $resolved['cart'];
        if (!is_array($cart)) {
            return Response::html('<h1>Cart unavailable</h1><p>Please try again.</p>', 500);
        }
        $this->sales->addVariantItem($cart, $artwork, $variant, $quantity, $priceCents, $shipping);

        return new Response('', 303, ['Location' => '/cart', 'Set-Cookie' => (string) $resolved['set_cookie']]);
    }

    public function cart(Request $request, TenantContext $tenant): Response
    {
        $identity = new CartIdentityService($this->pdo);
        $resolved = $identity->resolveCartForRequest($tenant, $request, false);
        $cart = is_array($resolved['cart']) ? $resolved['cart'] : null;
        $items = $cart ? $this->sales->items($cart) : [];

        $csrf = $this->e($this->csrf->getOrCreate());
        $rows = '';
        $subtotal = 0;
        $shippingTotal = 0;
        foreach ($items as $item) {
            $line = (int) $item['quantity'] * (int) $item['unit_price_cents'];
            $shipping = $this->lineShippingCents($item);
            $subtotal += $line;
            $shippingTotal += $shipping;
            $details = $this->cartItemDetails($item);
            $rows .= '<tr><td>' . $this->e((string) $item['title_snapshot']) . $details . '</td><td><input type="number" name="quantity[' . (int) $item['id'] . ']" min="0" value="' . (int) $item['quantity'] . '"></td><td>' . $this->money((int) $item['unit_price_cents']) . '</td><td>' . $this->money($shipping) . '</td><td>' . $this->money($line + $shipping) . '</td></tr>';
        }

        $customerEmail = $cart ? $this->e((string) (($cart['contact_email'] ?? '') ?: ($cart['customer_email'] ?? ''))) : '';
        $customerName = $cart ? $this->e((string) ($cart['customer_name'] ?? '')) : '';
        $fees = $this->saleEconomics($tenant, $items);
        $total = $subtotal + $shippingTotal;
        $feeDisclosure = $items === [] ? '' : '<div class="sales-fee-disclosure"><p><strong>Seller payout disclosure:</strong> the artist receives sale amount minus ArtsFolio commission and credit card charges.</p><p>On this cart: platform commission ' . $this->money($fees['commission_cents']) . ', estimated credit card charges ' . $this->money($fees['credit_card_fee_cents']) . ', estimated artist proceeds ' . $this->money($fees['seller_net_cents']) . '.</p></div>';
        $customerFields = '<fieldset class="tenant-form"><legend>Cart contact</legend><label>Name<input name="customer_name" value="' . $customerName . '" autocomplete="name"></label><label>Email<input type="email" name="customer_email" value="' . $customerEmail . '" autocomplete="email" required></label><p class="muted">Email lets ArtsFolio send checkout reminders for abandoned carts.</p></fieldset>';
        $checkout = $items === [] ? '<p>Your cart is empty.</p>' : $customerFields . '<div class="cart-totals"><p><strong>Subtotal:</strong> ' . $this->money($subtotal) . '</p><p><strong>Shipping:</strong> ' . $this->money($shippingTotal) . '</p><p><strong>Total:</strong> ' . $this->money($total) . '</p></div>' . $feeDisclosure . '<button type="submit" formaction="/cart/update">Update cart</button> <button type="submit" formaction="/cart/checkout">Checkout with Stripe</button>';
        $body = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Cart</title><link rel="stylesheet" href="/assets/site.css"></head><body><main class="site-main tenant-content-surface"><h1>Shopping cart</h1><form class="plan-edit-form cart-review-form" method="post"><input type="hidden" name="csrf_token" value="' . $csrf . '"><table class="admin-table cart-review-table"><thead><tr><th>Artwork</th><th>Qty</th><th>Unit</th><th>Shipping</th><th>Total</th></tr></thead><tbody>' . $rows . '</tbody></table>' . $checkout . '</form><p><a href="/portfolio">Continue browsing</a></p></main></body></html>';

        return Response::html($body, 200, $resolved['set_cookie'] ? ['Set-Cookie' => (string) $resolved['set_cookie']] : []);
    }

    public function update(Request $request, TenantContext $tenant): Response
    {
        if (!$this->verifyCsrf()) {
            return new Response('', 303, ['Location' => '/cart?error=security']);
        }
        $identity = new CartIdentityService($this->pdo);
        $resolved = $identity->resolveCartForRequest($tenant, $request, false);
        $cart = is_array($resolved['cart']) ? $resolved['cart'] : null;
        if ($cart) {
            $this->saveCartContact((int) $cart['id']);
            foreach ((array) ($_POST['quantity'] ?? []) as $itemId => $qty) {
                $this->sales->updateQuantity((int) $cart['id'], (int) $itemId, (int) $qty);
            }
        }
        return new Response('', 303, ['Location' => '/cart']);
    }

    public function bridgePixel(Request $request, TenantContext $tenant): Response
    {
        try {
            $resolved = (new CartIdentityService($this->pdo))->consumeBridgeToken($tenant, $request, trim((string) ($_GET['token'] ?? '')));
            $body = base64_decode('R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==', true) ?: '';
            return new Response($body, 200, [
                'Content-Type' => 'image/gif',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'Set-Cookie' => $resolved['set_cookie'],
            ]);
        } catch (Throwable) {
            return new Response('', 204, ['Cache-Control' => 'no-store, no-cache, must-revalidate']);
        }
    }

    public function bridge(Request $request, TenantContext $tenant): Response
    {
        $next = trim((string) ($_GET['next'] ?? '/cart'));
        if ($next === '' || $next[0] !== '/' || str_starts_with($next, '//')) {
            $next = '/cart';
        }
        try {
            $resolved = (new CartIdentityService($this->pdo))->consumeBridgeToken($tenant, $request, trim((string) ($_GET['token'] ?? '')));
            return new Response('', 303, ['Location' => $next, 'Set-Cookie' => $resolved['set_cookie']]);
        } catch (Throwable) {
            return new Response('', 303, ['Location' => '/cart?bridge=expired']);
        }
    }

    public function checkout(Request $request, TenantContext $tenant): Response
    {
        if (!$this->verifyCsrf()) {
            return new Response('', 303, ['Location' => '/cart?error=security']);
        }
        if (!$this->salesEnabled($tenant)) {
            return Response::html('<h1>Checkout unavailable</h1><p>Online sales are available on paid ArtsFolio plans.</p>', 403);
        }

        $resolved = (new CartIdentityService($this->pdo))->resolveCartForRequest($tenant, $request, false);
        $cart = is_array($resolved['cart']) ? $resolved['cart'] : null;
        if (!$cart) {
            return new Response('', 303, ['Location' => '/cart']);
        }

        $this->saveCartContact((int) $cart['id']);
        $items = $this->sales->items($cart);
        $fees = $this->saleEconomics($tenant, $items);
        $commissionCents = (int) $fees['commission_cents'];
        $scheme = 'https';
        $host = $request->host();
        $successUrl = $scheme . '://' . $host . '/checkout/success?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $scheme . '://' . $host . '/cart?checkout=cancelled';
        $order = null;

        try {
            $order = $this->sales->createOrderFromCart($tenant, $cart, $items, $commissionCents, (int) $fees['credit_card_fee_cents'], (int) $fees['seller_net_cents']);
            $session = (new StripeCheckoutService())->createSession(
                (string) $this->platformSettings->get('stripe_secret_key', ''),
                $order,
                $items,
                $successUrl,
                $cancelUrl,
                trim((string) $this->tenantSettings->get($tenant, 'stripe_connected_account_id', '')) ?: null,
                $commissionCents + (int) $fees['credit_card_fee_cents'],
            );
            $this->sales->attachCheckoutSession((int) $order['id'], (string) $session['id'], (string) $session['url']);
            $this->sales->markCartCheckedOut((int) $cart['id']);
            return new Response('', 303, ['Location' => (string) $session['url'], 'Set-Cookie' => (new CartIdentityService($this->pdo))->expireCartCookie()]);
        } catch (Throwable $e) {
            if (is_array($order) && isset($order['id'])) {
                $this->sales->releaseReservationsForOrder((int) $order['id']);
            }
            return Response::html('<h1>Checkout could not be started</h1><p>' . $this->e($e->getMessage()) . '</p><p><a href="/cart">Return to cart</a></p>', 502);
        }
    }

    public function success(Request $request, TenantContext $tenant): Response
    {
        $sessionId = trim((string) ($_GET['session_id'] ?? ''));
        $order = $sessionId !== '' ? $this->sales->orderBySession($tenant, $sessionId) : null;
        $number = $order ? $this->e((string) $order['order_number']) : 'your order';
        return Response::html('<!doctype html><html><head><meta charset="utf-8"><title>Order received</title><link rel="stylesheet" href="/assets/site.css"></head><body><main class="site-main tenant-content-surface"><h1>Order received</h1><p>Thank you. We received ' . $number . ' and the artist will follow up as it moves through the sales workflow.</p><p><a href="/portfolio">Return to portfolio</a></p></main></body></html>');
    }

    private function saveCartContact(int $cartId): void
    {
        $email = strtolower(trim((string) ($_POST['customer_email'] ?? '')));
        $name = trim((string) ($_POST['customer_name'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        try {
            $stmt = $this->pdo->prepare('UPDATE sales_carts SET customer_email = :email, contact_email = :email, customer_name = :name, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmt->execute(['id' => $cartId, 'email' => $email, 'name' => $name !== '' ? $name : null]);
        } catch (Throwable) {
            $stmt = $this->pdo->prepare('UPDATE sales_carts SET customer_email = :email, customer_name = :name, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmt->execute(['id' => $cartId, 'email' => $email, 'name' => $name !== '' ? $name : null]);
        }
    }

    private function salesEnabled(TenantContext $tenant): bool
    {
        $stmt = $this->pdo->prepare('SELECT p.monthly_price_cents, COALESCE(p.allow_sales, 0) AS allow_sales FROM tenant_plan_assignments tpa JOIN plans p ON p.id = tpa.plan_id WHERE tpa.tenant_id = :tenant_id AND tpa.status IN ("trial", "active", "manual") LIMIT 1');
        $stmt->execute(['tenant_id' => $tenant->tenantId]);
        $row = $stmt->fetch();
        return $row && (int) ($row['monthly_price_cents'] ?? 0) > 0 && (int) ($row['allow_sales'] ?? 0) === 1;
    }

    /** @param array<string,mixed> $config @param array<string,mixed> $variant @return array{shipping_price_cents:int,shipping_additional_item_cents:int} */
    private function shippingForVariant(array $config, array $variant): array
    {
        $mode = (string) ($config['shipping_mode'] ?? 'none');
        if ($mode === 'variant') {
            return [
                'shipping_price_cents' => max(0, (int) ($variant['shipping_price_cents'] ?? 0)),
                'shipping_additional_item_cents' => max(0, (int) ($variant['shipping_additional_item_cents'] ?? 0)),
            ];
        }
        if ($mode === 'none') {
            return ['shipping_price_cents' => 0, 'shipping_additional_item_cents' => 0];
        }

        return [
            'shipping_price_cents' => max(0, (int) ($config['shipping_price_cents'] ?? 0)),
            'shipping_additional_item_cents' => $mode === 'flat_per_item' ? max(0, (int) ($config['shipping_additional_item_cents'] ?? 0)) : 0,
        ];
    }

    private function lineShippingCents(array $item): int
    {
        $quantity = max(1, (int) ($item['quantity'] ?? 1));
        return max(0, (int) ($item['shipping_price_cents'] ?? 0)) + max(0, $quantity - 1) * max(0, (int) ($item['shipping_additional_item_cents'] ?? 0));
    }

    private function cartItemDetails(array $item): string
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

    private function saleEconomics(TenantContext $tenant, array $items): array
    {
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += (int) $item['quantity'] * (int) $item['unit_price_cents'];
        }
        $commissionBasisPoints = max(0, min(10000, (int) $this->platformSettings->get('platform_sales_commission_basis_points', '500')));
        $planFees = $this->planPaymentFees($tenant);
        $commission = (int) round($subtotal * ($commissionBasisPoints / 10000));
        $creditCardFee = (int) round($subtotal * (((int) $planFees['credit_card_fee_basis_points']) / 10000)) + (int) $planFees['credit_card_fixed_fee_cents'];
        return [
            'subtotal_cents' => $subtotal,
            'commission_cents' => $commission,
            'credit_card_fee_cents' => $creditCardFee,
            'seller_net_cents' => max(0, $subtotal - $commission - $creditCardFee),
        ];
    }

    private function planPaymentFees(TenantContext $tenant): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT COALESCE(p.credit_card_fee_basis_points, 290) AS credit_card_fee_basis_points, COALESCE(p.credit_card_fixed_fee_cents, 30) AS credit_card_fixed_fee_cents FROM tenant_plan_assignments tpa JOIN plans p ON p.id = tpa.plan_id WHERE tpa.tenant_id = :tenant_id AND tpa.status IN ("trial", "active", "manual") ORDER BY tpa.id DESC LIMIT 1');
            $stmt->execute(['tenant_id' => $tenant->tenantId]);
            $row = $stmt->fetch();
            if ($row) {
                return [
                    'credit_card_fee_basis_points' => max(0, min(10000, (int) ($row['credit_card_fee_basis_points'] ?? 290))),
                    'credit_card_fixed_fee_cents' => max(0, (int) ($row['credit_card_fixed_fee_cents'] ?? 30)),
                ];
            }
        } catch (Throwable) {
            // Migration may not have reached older environments yet. Use Stripe's common public baseline.
        }
        return ['credit_card_fee_basis_points' => 290, 'credit_card_fixed_fee_cents' => 30];
    }

    private function verifyCsrf(): bool
    {
        return $this->csrf->verify((string) ($_POST['csrf_token'] ?? ''));
    }

    private function money(int $cents): string
    {
        return '$' . number_format($cents / 100, 2);
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
