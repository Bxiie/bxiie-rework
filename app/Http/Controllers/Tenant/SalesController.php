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
use App\Tenant\Sales\ShippingProfileService;
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
        try {

            if (!$this->verifyCsrf()) {
                return Response::html('<h1>Security check failed</h1><p>Please return to the artwork page and try again.</p>', 422);
            }
            if (!$this->salesEnabled($tenant)) {
                return $this->tenantPageResponse($tenant, 'Checkout unavailable', '<h1>Checkout unavailable</h1><p>Online sales are not enabled for this artist plan.</p><p><a href="/cart">Return to cart</a></p>', 403);
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
        
        } catch (Throwable $e) {
            $this->logCartAddFailure($tenant, $request, $e);

            return Response::html('<h1>Cart error</h1><p>The item could not be added to the cart. The exact error has been written to <code>storage/logs/cart_add.log</code> or <code>/tmp/artsfolio_cart_add.log</code> with the marker <code>[ArtsFolio cart/add]</code>.</p><p><a href="javascript:history.back()">Return to artwork</a></p>', 500);
        }
    }

    public function cart(Request $request, TenantContext $tenant): Response
    {
        $this->releaseCancelledCheckout($tenant);

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
        $content = '<h1>Shopping cart</h1><form class="plan-edit-form cart-review-form" method="post"><input type="hidden" name="csrf_token" value="' . $csrf . '"><table class="admin-table cart-review-table"><thead><tr><th>Artwork</th><th>Qty</th><th>Unit</th><th>Shipping</th><th>Total</th></tr></thead><tbody>' . $rows . '</tbody></table>' . $checkout . '</form><p><a href="/portfolio">Continue browsing</a></p>';

        return $this->tenantPageResponse($tenant, 'Shopping cart', $content, 200, $resolved['set_cookie'] ? ['Set-Cookie' => (string) $resolved['set_cookie']] : []);
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
            return $this->tenantPageResponse($tenant, 'Checkout unavailable', '<h1>Checkout unavailable</h1><p>Online sales are not enabled for this artist plan.</p><p><a href="/cart">Return to cart</a></p>', 403);
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
        $order = null;

        try {
            $order = $this->sales->createOrderFromCart($tenant, $cart, $items, $commissionCents, (int) $fees['credit_card_fee_cents'], (int) $fees['seller_net_cents']);
            $cancelUrl = $scheme . '://' . $host . '/cart?checkout=cancelled&order_id=' . (int) $order['id'];
            $customerEmail = strtolower(trim((string) ($_POST['customer_email'] ?? (($cart['contact_email'] ?? '') ?: ($cart['customer_email'] ?? '')))));
            $session = (new StripeCheckoutService())->createSession(
                (string) $this->platformSettings->get('stripe_secret_key', ''),
                $order,
                $items,
                $successUrl,
                $cancelUrl,
                trim((string) $this->tenantSettings->get($tenant, 'stripe_connected_account_id', '')) ?: null,
                $commissionCents + (int) $fees['credit_card_fee_cents'],
                (int) $fees['shipping_cents'],
                $customerEmail !== '' ? $customerEmail : null,
            );
            $this->sales->attachCheckoutSession((int) $order['id'], (string) $session['id'], (string) $session['url']);

            // Keep the active cart cookie and cart row in place while the buyer
            // is at Stripe. If they cancel and return, /cart can release this
            // checkout attempt's reservations and show the original cart again.
            return new Response('', 303, ['Location' => (string) $session['url']]);
        } catch (Throwable $e) {
            if (is_array($order) && isset($order['id'])) {
                $this->sales->releaseReservationsForOrder((int) $order['id']);
            }
            return $this->tenantPageResponse($tenant, 'Checkout could not be started', '<h1>Checkout could not be started</h1><p>' . $this->e($e->getMessage()) . '</p><p><a href="/cart">Return to cart</a></p>', 502);
        }
    }

    public function success(Request $request, TenantContext $tenant): Response
    {
        $sessionId = trim((string) ($_GET['session_id'] ?? ''));
        $order = $sessionId !== '' ? $this->sales->orderBySession($tenant, $sessionId) : null;
        $number = $order ? $this->e((string) $order['order_number']) : 'your order';
        return $this->tenantPageResponse($tenant, 'Order received', '<h1>Order received</h1><p>Thank you. We received ' . $number . ' and the artist will follow up as it moves through the sales workflow.</p><p><a href="/portfolio">Return to portfolio</a></p>');
    }


    /**
     * Log cart-add failures with enough request context to diagnose production 500s without exposing internals to buyers.
     */
    private function logCartAddFailure(TenantContext $tenant, Request $request, Throwable $e): void
    
    {
        $payload = [
            'tenant_id' => (int) $tenant->tenantId,
            'host' => $request->host(),
            'path' => $request->path(),
            'artwork_id' => (int) ($_POST['artwork_id'] ?? 0),
            'variant_id' => (int) ($_POST['variant_id'] ?? 0),
            'quantity' => (int) ($_POST['quantity'] ?? 0),
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];

        $line = '[ArtsFolio cart/add] ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

        $root = dirname(__DIR__, 4);
        $logDir = $root . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $written = false;
        $logPath = $logDir . '/cart_add.log';
        if (is_dir($logDir) && (is_writable($logDir) || (is_file($logPath) && is_writable($logPath)))) {
            $written = @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX) !== false;
        }

        if (!$written) {
            @file_put_contents('/tmp/artsfolio_cart_add.log', $line, FILE_APPEND | LOCK_EX);
        }

        error_log($line);
    }


    /**
     * Renders cart and checkout utility pages inside the tenant public chrome.
     *
     * SalesController cannot call HomeController's private layout method, so it
     * keeps a deliberately small branded shell here. This prevents /cart,
     * checkout errors, and order success pages from falling back to unbranded
     * utility documents.
     *
     * @param array<string,string> $headers
     */
    private function tenantPageResponse(TenantContext $tenant, string $title, string $body, int $status = 200, array $headers = []): Response
    {
        $baseHeaders = [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'Vary' => 'Cookie',
        ];

        return Response::html($this->tenantPage($tenant, $title, $body), $status, array_merge($baseHeaders, $headers));
    }

    private function tenantPage(TenantContext $tenant, string $title, string $body): string
    {
        $siteTitle = $this->e((string) $this->tenantSettings->get($tenant, 'site_title', $tenant->name));
        $browserTitle = $this->e($title . ' · ' . html_entity_decode($siteTitle, ENT_QUOTES, 'UTF-8'));
        $homeTab = $this->e((string) $this->tenantSettings->get($tenant, 'home_tab', 'Home'));
        $portfolioTab = $this->e((string) $this->tenantSettings->get($tenant, 'portfolio_tab', 'Portfolio'));
        $aboutTab = $this->e((string) $this->tenantSettings->get($tenant, 'about_tab', 'About'));
        $contactTab = $this->e((string) $this->tenantSettings->get($tenant, 'contact_tab', 'Contact'));
        $portfolioSlug = $this->e((string) $this->tenantSettings->get($tenant, 'portfolio_slug', 'portfolio'));
        $aboutSlug = $this->e((string) $this->tenantSettings->get($tenant, 'about_slug', 'about'));
        $contactSlug = $this->e((string) $this->tenantSettings->get($tenant, 'contact_slug', 'contact'));
        $primaryColor = $this->e((string) $this->tenantSettings->get($tenant, 'primary_color', '#111111'));
        $accentColor = $this->e((string) $this->tenantSettings->get($tenant, 'accent_color', '#c9a85f'));
        $backgroundColor = $this->e((string) $this->tenantSettings->get($tenant, 'background_color', '#f7f2e8'));
        $textColor = $this->e((string) $this->tenantSettings->get($tenant, 'text_color', '#1f1a14'));

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$browserTitle}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Artist portfolio cart">
    <link rel="stylesheet" href="/assets/site.css?v=20260620-typography-apply">
    <link rel="stylesheet" href="/tenant.css">
</head>
<body class="tenant-cart-page" style="--primary:{$primaryColor};--accent:{$accentColor};--bg:{$backgroundColor};--text-color:{$textColor};">
<header class="site-header">
    <a class="brand" href="/">{$siteTitle}</a>
    <nav>
        <a href="/">{$homeTab}</a>
        <a href="/{$portfolioSlug}">{$portfolioTab}</a>
        <a href="/{$aboutSlug}">{$aboutTab}</a>
        <a href="/{$contactSlug}">{$contactTab}</a>
        {$this->cartChromeForTenantPage($tenant)}
    </nav>
</header>
<main class="site-main tenant-content-surface cart-page-surface">
{$body}
</main>
</body>
</html>
HTML;
    }

    /**
     * Release reservations when Stripe sends the buyer back through cancel_url.
     *
     * The order id is tenant-checked before release. The cart remains active so
     * the buyer can edit the cart or start checkout again without rebuilding it.
     */
    private function releaseCancelledCheckout(TenantContext $tenant): void
    {
        if ((string) ($_GET['checkout'] ?? '') !== 'cancelled') {
            return;
        }
        $orderId = (int) ($_GET['order_id'] ?? 0);
        if ($orderId <= 0) {
            return;
        }
        try {
            $order = $this->sales->order($tenant, $orderId);
            if ($order && (string) ($order['payment_status'] ?? '') === 'checkout_pending') {
                $this->sales->releaseReservationsForOrder($orderId, 'checkout_cancelled');
            }
        } catch (Throwable) {
            // Cart display should not fail because a cancellation cleanup has
            // already happened or because the payment attempt was not found.
        }
    }

    /**
     * Render a cart link inside SalesController's tenant shell only when the
     * active tenant cart has items. This mirrors HomeController cart chrome.
     */
    private function cartChromeForTenantPage(TenantContext $tenant): string
    {
        try {
            $request = Request::fromGlobals();
            $resolved = (new CartIdentityService($this->pdo))->resolveCartForRequest($tenant, $request, false);
            $cart = is_array($resolved['cart'] ?? null) ? $resolved['cart'] : null;
            if (!$cart) {
                return '';
            }
            $summary = $this->sales->cartSummary($cart);
            if ((int) ($summary['item_count'] ?? 0) <= 0) {
                return '';
            }

            return '<a class="site-cart-link tenant-cart-link" href="/cart" aria-label="Shopping cart">Cart ('
                . (int) $summary['item_count']
                . ') '
                . $this->money((int) ($summary['total_cents'] ?? 0))
                . '</a>';
        } catch (Throwable) {
            return '';
        }
    }

    private function saveCartContact(int $cartId): void
    {
        $email = strtolower(trim((string) ($_POST['customer_email'] ?? '')));
        $name = trim((string) ($_POST['customer_name'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        try {
            $stmt = $this->pdo->prepare('UPDATE sales_carts SET customer_email = :customer_email, contact_email = :contact_email, customer_name = :name, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmt->execute([
                'id' => $cartId,
                'customer_email' => $email,
                'contact_email' => $email,
                'name' => $name !== '' ? $name : null,
            ]);
        } catch (Throwable) {
            $stmt = $this->pdo->prepare('UPDATE sales_carts SET customer_email = :email, customer_name = :name, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmt->execute(['id' => $cartId, 'email' => $email, 'name' => $name !== '' ? $name : null]);
        }
    }

    private function salesEnabled(TenantContext $tenant): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT COALESCE(p.allow_sales, 0) AS allow_sales
                 FROM tenant_plan_assignments tpa
                 JOIN plans p ON p.id = tpa.plan_id
                 WHERE tpa.tenant_id = :tenant_id
                   AND tpa.status IN ("trial", "active", "manual")
                 ORDER BY tpa.id DESC
                 LIMIT 1'
            );
            $stmt->execute(['tenant_id' => $tenant->tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row && (int) ($row['allow_sales'] ?? 0) === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param array<string,mixed> $config @param array<string,mixed> $variant @return array<string,mixed> */
    private function shippingForVariant(array $config, array $variant): array
    {
        $profileId = (int) ($variant['shipping_profile_id'] ?? 0);
        if ($profileId <= 0) {
            $profileId = (int) ($config['shipping_profile_id'] ?? 0);
        }
        $profile = (new ShippingProfileService($this->pdo))->profile((int) ($config['tenant_id'] ?? $variant['tenant_id'] ?? 0), $profileId > 0 ? $profileId : null);
        if ($profile && (int) ($profile['allow_checkout'] ?? 1) !== 1) {
            throw new RuntimeException('This item requires a shipping quote before checkout.');
        }
        if ($profile) {
            $mode = (string) ($profile['mode'] ?? 'flat_profile');
            $base = max(0, (int) ($profile['base_shipping_cents'] ?? 0));
            $additional = max(0, (int) ($profile['additional_item_cents'] ?? 0));
            if ($mode === 'free') {
                $base = 0;
                $additional = 0;
            } elseif ($mode === 'flat_profile') {
                $additional = 0;
            } elseif ($mode === 'per_item') {
                $additional = $base;
            }

            return [
                'shipping_price_cents' => $base,
                'shipping_additional_item_cents' => $additional,
                'shipping_profile_id' => (int) $profile['id'],
                'shipping_profile_name' => (string) $profile['name'],
                'shipping_profile_mode' => $mode,
                'shipping_profile_max_cents' => $profile['max_shipping_cents'] !== null ? max(0, (int) $profile['max_shipping_cents']) : null,
            ];
        }

        $mode = (string) ($config['shipping_mode'] ?? 'none');
        if ($mode === 'variant') {
            return [
                'shipping_price_cents' => max(0, (int) ($variant['shipping_price_cents'] ?? 0)),
                'shipping_additional_item_cents' => max(0, (int) ($variant['shipping_additional_item_cents'] ?? 0)),
                'shipping_profile_id' => null,
                'shipping_profile_name' => null,
                'shipping_profile_mode' => 'legacy_variant',
                'shipping_profile_max_cents' => null,
            ];
        }
        if ($mode === 'none') {
            return ['shipping_price_cents' => 0, 'shipping_additional_item_cents' => 0, 'shipping_profile_id' => null, 'shipping_profile_name' => null, 'shipping_profile_mode' => 'legacy_none', 'shipping_profile_max_cents' => null];
        }

        return [
            'shipping_price_cents' => max(0, (int) ($config['shipping_price_cents'] ?? 0)),
            'shipping_additional_item_cents' => $mode === 'flat_per_item' ? max(0, (int) ($config['shipping_additional_item_cents'] ?? 0)) : 0,
            'shipping_profile_id' => null,
            'shipping_profile_name' => null,
            'shipping_profile_mode' => 'legacy_' . $mode,
            'shipping_profile_max_cents' => null,
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
        $shipping = 0;
        foreach ($items as $item) {
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $subtotal += $quantity * (int) $item['unit_price_cents'];
            $shipping += $this->lineShippingCents($item);
        }
        $total = $subtotal + $shipping;
        $commissionBasisPoints = max(0, min(10000, (int) $this->platformSettings->get('platform_sales_commission_basis_points', '500')));
        $planFees = $this->planPaymentFees($tenant);
        $commission = (int) round($subtotal * ($commissionBasisPoints / 10000));
        $creditCardFee = (int) round($total * (((int) $planFees['credit_card_fee_basis_points']) / 10000)) + (int) $planFees['credit_card_fixed_fee_cents'];
        return [
            'subtotal_cents' => $subtotal,
            'shipping_cents' => $shipping,
            'total_cents' => $total,
            'commission_cents' => $commission,
            'credit_card_fee_cents' => $creditCardFee,
            'seller_net_cents' => max(0, $total - $commission - $creditCardFee),
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
        return $this->csrf->validate((string) ($_POST['csrf_token'] ?? ''));
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
