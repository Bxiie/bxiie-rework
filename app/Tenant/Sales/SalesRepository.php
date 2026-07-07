<?php

declare(strict_types=1);

// ARTSFOLIO_LOW_STOCK_TRACKING_MARKER: multiple-item stock alerts use original_inventory_quantity and low_stock_notification_sent_at.

namespace App\Tenant\Sales;

use App\Platform\Tenancy\TenantContext;
use PDO;
use RuntimeException;

/**
 * Persists tenant-scoped carts, orders, and sales workflow state.
 */
final class SalesRepository
{
    // ARTWORK_LOW_STOCK_TRACKING_MARKER: multiple-item stock tracks original_inventory_quantity and low_stock_notification_sent_at so tenant admins can be emailed at 10%.
    private const RESERVATION_MINUTES = 35;

    public function __construct(private readonly PDO $pdo) {}

    public function cartForToken(TenantContext $tenant, string $token): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sales_carts WHERE tenant_id = :tenant_id AND cart_token = :token AND status = "active" LIMIT 1');
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'token' => $token]);
        $cart = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($cart) {
            return $cart;
        }

        $insert = $this->pdo->prepare('INSERT INTO sales_carts (tenant_id, cart_token, status) VALUES (:tenant_id, :token, "active")');
        $insert->execute(['tenant_id' => $tenant->tenantId, 'token' => $token]);

        return ['id' => (int) $this->pdo->lastInsertId(), 'tenant_id' => $tenant->tenantId, 'cart_token' => $token, 'status' => 'active'];
    }

    public function artworkForPurchase(TenantContext $tenant, int $artworkId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT a.id, a.tenant_id, a.title, a.slug, m.uuid AS media_uuid, a.sale_status, a.price, a.is_one_off, a.inventory_quantity, GREATEST(0, a.inventory_quantity - COALESCE((SELECT SUM(r.quantity) FROM sales_inventory_reservations r WHERE r.artwork_id = a.id AND r.status = "reserved" AND r.expires_at > UTC_TIMESTAMP()), 0)) AS available_quantity FROM artworks a LEFT JOIN media_assets m ON m.id = a.primary_media_id WHERE a.tenant_id = :tenant_id AND a.id = :id AND a.status = "published" LIMIT 1');
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'id' => $artworkId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function addItem(array $cart, array $artwork, int $quantity, int $unitPriceCents): void
    {
        $quantity = max(1, $quantity);
        if ((int) ($artwork['is_one_off'] ?? 1) === 1) {
            $quantity = 1;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO sales_cart_items (cart_id, artwork_id, quantity, unit_price_cents, title_snapshot, media_uuid_snapshot)
             VALUES (:cart_id, :artwork_id, :quantity, :unit_price_cents, :title, :media_uuid)
             ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + VALUES(quantity), :max_quantity), unit_price_cents = VALUES(unit_price_cents), title_snapshot = VALUES(title_snapshot), media_uuid_snapshot = VALUES(media_uuid_snapshot), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'cart_id' => (int) $cart['id'],
            'artwork_id' => (int) $artwork['id'],
            'quantity' => $quantity,
            'max_quantity' => max(1, (int) ($artwork['available_quantity'] ?? $artwork['inventory_quantity'] ?? 1)),
            'unit_price_cents' => $unitPriceCents,
            'title' => (string) $artwork['title'],
            'media_uuid' => $artwork['media_uuid'] ?? null,
        ]);
    }

    public function items(array $cart): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sales_cart_items WHERE cart_id = :cart_id ORDER BY id');
        $stmt->execute(['cart_id' => (int) $cart['id']]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateQuantity(int $cartId, int $itemId, int $quantity): void
    {
        if ($quantity <= 0) {
            $stmt = $this->pdo->prepare('DELETE FROM sales_cart_items WHERE cart_id = :cart_id AND id = :id');
            $stmt->execute(['cart_id' => $cartId, 'id' => $itemId]);
            return;
        }
        $stmt = $this->pdo->prepare('UPDATE sales_cart_items SET quantity = :quantity, updated_at = CURRENT_TIMESTAMP WHERE cart_id = :cart_id AND id = :id');
        $stmt->execute(['cart_id' => $cartId, 'id' => $itemId, 'quantity' => $quantity]);
    }

    /**
     * Returns the sale configuration row for a tenant artwork.
     *
     * @return array<string,mixed>|null
     */
    public function saleConfigForArtwork(TenantContext $tenant, int $artworkId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM artwork_sale_config WHERE tenant_id = :tenant_id AND artwork_id = :artwork_id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'artwork_id' => $artworkId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Returns active sale variants with current reservation-adjusted availability.
     *
     * @return list<array<string,mixed>>
     */
    public function variantsForArtwork(TenantContext $tenant, int $artworkId, bool $activeOnly = true): array
    {
        $activeClause = $activeOnly ? 'AND v.is_active = 1' : '';
        $stmt = $this->pdo->prepare(
            'SELECT v.*,
                    GREATEST(0, v.inventory_quantity - COALESCE((
                        SELECT SUM(r.quantity)
                        FROM sales_inventory_reservations r
                        WHERE r.variant_id = v.id
                          AND r.status = "reserved"
                          AND r.expires_at > UTC_TIMESTAMP()
                    ), 0)) AS available_quantity
             FROM artwork_sale_variants v
             WHERE v.tenant_id = :tenant_id
               AND v.artwork_id = :artwork_id
               ' . $activeClause . '
             ORDER BY v.sort_order ASC, v.id ASC'
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'artwork_id' => $artworkId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Returns one active purchasable variant for an artwork.
     *
     * @return array<string,mixed>|null
     */
    public function variantForPurchase(TenantContext $tenant, int $artworkId, int $variantId): ?array
    {
        foreach ($this->variantsForArtwork($tenant, $artworkId, true) as $variant) {
            if ((int) $variant['id'] === $variantId) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * Adds a variant-aware row to the cart while snapshotting price, option, and shipping fields.
     *
     * @param array<string,mixed> $cart
     * @param array<string,mixed> $artwork
     * @param array<string,mixed> $variant
     * @param array{shipping_price_cents:int,shipping_additional_item_cents:int} $shipping
     */
    public function addVariantItem(array $cart, array $artwork, array $variant, int $quantity, int $unitPriceCents, array $shipping): void
    {
        $quantity = max(1, $quantity);
        $maxQuantity = max(1, (int) ($variant['available_quantity'] ?? $variant['inventory_quantity'] ?? 1));
        $stmt = $this->pdo->prepare(
            'INSERT INTO sales_cart_items (
                cart_id, artwork_id, variant_id, quantity, unit_price_cents,
                shipping_profile_id, shipping_profile_name_snapshot, shipping_profile_mode_snapshot,
                shipping_price_cents, shipping_additional_item_cents, shipping_profile_max_cents,
                title_snapshot, variant_label_snapshot, size_value_snapshot, gender_value_snapshot, media_uuid_snapshot
             ) VALUES (
                :cart_id, :artwork_id, :variant_id, :quantity, :unit_price_cents,
                :shipping_profile_id, :shipping_profile_name, :shipping_profile_mode,
                :shipping_price_cents, :shipping_additional_item_cents, :shipping_profile_max_cents,
                :title, :variant_label, :size_value, :gender_value, :media_uuid
             )
             ON DUPLICATE KEY UPDATE
                quantity = LEAST(quantity + VALUES(quantity), :max_quantity),
                unit_price_cents = VALUES(unit_price_cents),
                shipping_profile_id = VALUES(shipping_profile_id),
                shipping_profile_name_snapshot = VALUES(shipping_profile_name_snapshot),
                shipping_profile_mode_snapshot = VALUES(shipping_profile_mode_snapshot),
                shipping_profile_max_cents = VALUES(shipping_profile_max_cents),
                shipping_price_cents = VALUES(shipping_price_cents),
                shipping_additional_item_cents = VALUES(shipping_additional_item_cents),
                title_snapshot = VALUES(title_snapshot),
                variant_label_snapshot = VALUES(variant_label_snapshot),
                size_value_snapshot = VALUES(size_value_snapshot),
                gender_value_snapshot = VALUES(gender_value_snapshot),
                media_uuid_snapshot = VALUES(media_uuid_snapshot),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'cart_id' => (int) $cart['id'],
            'artwork_id' => (int) $artwork['id'],
            'variant_id' => (int) $variant['id'],
            'quantity' => min($quantity, $maxQuantity),
            'max_quantity' => $maxQuantity,
            'unit_price_cents' => $unitPriceCents,
            'shipping_profile_id' => isset($shipping['shipping_profile_id']) ? (int) $shipping['shipping_profile_id'] : null,
            'shipping_profile_name' => $shipping['shipping_profile_name'] ?? null,
            'shipping_profile_mode' => $shipping['shipping_profile_mode'] ?? null,
            'shipping_profile_max_cents' => isset($shipping['shipping_profile_max_cents']) ? (int) $shipping['shipping_profile_max_cents'] : null,
            'shipping_price_cents' => max(0, (int) $shipping['shipping_price_cents']),
            'shipping_additional_item_cents' => max(0, (int) $shipping['shipping_additional_item_cents']),
            'title' => (string) $artwork['title'],
            'variant_label' => (string) ($variant['variant_label'] ?? 'Default'),
            'size_value' => $variant['size_value'] ?? null,
            'gender_value' => $variant['gender_value'] ?? 'not_applicable',
            'media_uuid' => $artwork['media_uuid'] ?? null,
        ]);

        $touch = $this->pdo->prepare('UPDATE sales_carts SET last_item_added_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :cart_id');
        $touch->execute(['cart_id' => (int) $cart['id']]);
    }

    /**
     * Returns a compact non-empty cart summary for tenant chrome.
     *
     * @return array{item_count:int,subtotal_cents:int,shipping_cents:int,total_cents:int,cart_id:int}
     */
    public function cartSummary(array $cart): array
    {
        $items = $this->items($cart);
        $itemCount = 0;
        $subtotal = 0;
        $shipping = 0;
        foreach ($items as $item) {
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $itemCount += $quantity;
            $subtotal += $quantity * (int) ($item['unit_price_cents'] ?? 0);
        }
        $shipping = array_sum($this->shippingAllocations($items));

        return [
            'item_count' => $itemCount,
            'subtotal_cents' => $subtotal,
            'shipping_cents' => $shipping,
            'total_cents' => $subtotal + $shipping,
            'cart_id' => (int) $cart['id'],
        ];
    }

    public function createOrderFromCart(TenantContext $tenant, array $cart, array $items, int $commissionCents, int $creditCardFeeCents = 0, int $sellerNetCents = 0): array
    {
        if ($items === []) {
            throw new RuntimeException('Cart is empty.');
        }

        $subtotal = 0;
        foreach ($items as $item) {
            $quantity = max(1, (int) $item['quantity']);
            $subtotal += $quantity * (int) $item['unit_price_cents'];
        }
        $shippingAllocations = $this->shippingAllocations($items);
        $shippingTotal = array_sum($shippingAllocations);
        $total = $subtotal + $shippingTotal;
        $orderNumber = 'AF-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));

        $this->pdo->beginTransaction();
        try {
            $this->expireReservationsWithinTransaction();

            $cartLock = $this->pdo->prepare(
                'SELECT status FROM sales_carts WHERE id = :cart_id AND tenant_id = :tenant_id FOR UPDATE'
            );
            $cartLock->execute(['cart_id' => (int) $cart['id'], 'tenant_id' => $tenant->tenantId]);
            $cartStatus = $cartLock->fetchColumn();
            if ($cartStatus !== 'active') {
                throw new RuntimeException('This cart is no longer available for checkout.');
            }

            $pending = $this->pdo->prepare(
                'SELECT id FROM sales_orders
                 WHERE cart_id = :cart_id AND payment_status = "checkout_pending"
                 ORDER BY id DESC LIMIT 1'
            );
            $pending->execute(['cart_id' => (int) $cart['id']]);
            if ($pending->fetchColumn()) {
                throw new RuntimeException('Checkout is already in progress for this cart.');
            }

            $lockedItems = [];
            foreach ($items as $item) {
                $artworkId = (int) $item['artwork_id'];
                $variantId = (int) ($item['variant_id'] ?? 0);
                if ($variantId <= 0) {
                    throw new RuntimeException('A cart item is missing its sale variant. Please remove and re-add the item.');
                }

                $quantity = max(1, (int) $item['quantity']);
                $lock = $this->pdo->prepare(
                    'SELECT a.id AS artwork_id,
                            a.tenant_id,
                            a.sale_status,
                            c.sale_kind,
                            c.checkout_enabled,
                            v.id AS variant_id,
                            v.inventory_quantity,
                            v.is_active
                       FROM artworks a
                       JOIN artwork_sale_config c ON c.artwork_id = a.id AND c.tenant_id = a.tenant_id
                       JOIN artwork_sale_variants v ON v.artwork_id = a.id AND v.tenant_id = a.tenant_id
                      WHERE a.id = :artwork_id
                        AND a.tenant_id = :tenant_id
                        AND a.status = "published"
                        AND v.id = :variant_id
                      LIMIT 1
                      FOR UPDATE'
                );
                $lock->execute([
                    'artwork_id' => $artworkId,
                    'tenant_id' => $tenant->tenantId,
                    'variant_id' => $variantId,
                ]);
                $saleRow = $lock->fetch(PDO::FETCH_ASSOC);
                if (!$saleRow || (string) $saleRow['sale_status'] !== 'for_sale' || (int) $saleRow['checkout_enabled'] !== 1 || (int) $saleRow['is_active'] !== 1) {
                    throw new RuntimeException('An item in this cart is no longer available.');
                }
                if ((string) ($saleRow['sale_kind'] ?? 'one_off') === 'one_off') {
                    $quantity = 1;
                }

                $reservedStmt = $this->pdo->prepare(
                    'SELECT COALESCE(SUM(quantity), 0)
                       FROM sales_inventory_reservations
                      WHERE variant_id = :variant_id
                        AND status = "reserved"
                        AND expires_at > UTC_TIMESTAMP()'
                );
                $reservedStmt->execute(['variant_id' => $variantId]);
                $reserved = (int) $reservedStmt->fetchColumn();
                $available = max(0, (int) $saleRow['inventory_quantity'] - $reserved);
                if ($quantity > $available) {
                    throw new RuntimeException(sprintf(
                        '%s no longer has the requested quantity available.',
                        (string) ($item['title_snapshot'] ?? 'This item')
                    ));
                }

                $lockedItems[(int) $item['id']] = [
                    'artwork_id' => $artworkId,
                    'variant_id' => $variantId,
                    'quantity' => $quantity,
                ];
            }

            $sellerNetCents = $sellerNetCents > 0 ? $sellerNetCents : max(0, $total - $commissionCents - $creditCardFeeCents);
            $stmt = $this->pdo->prepare(
                'INSERT INTO sales_orders
                    (tenant_id, cart_id, order_number, workflow_status, payment_status, currency, subtotal_cents, shipping_cents, commission_cents, credit_card_fee_cents, seller_net_cents, total_cents)
                 VALUES
                    (:tenant_id, :cart_id, :order_number, "ordered", "checkout_pending", "usd", :subtotal, :shipping, :commission, :credit_card_fee, :seller_net, :total)'
            );
            $stmt->execute([
                'tenant_id' => $tenant->tenantId,
                'cart_id' => (int) $cart['id'],
                'order_number' => $orderNumber,
                'subtotal' => $subtotal,
                'shipping' => $shippingTotal,
                'commission' => $commissionCents,
                'credit_card_fee' => $creditCardFeeCents,
                'seller_net' => $sellerNetCents,
                'total' => $total,
            ]);
            $orderId = (int) $this->pdo->lastInsertId();

            $itemStmt = $this->pdo->prepare(
                'INSERT INTO sales_order_items
                    (order_id, artwork_id, variant_id, title_snapshot, variant_label_snapshot, size_value_snapshot, gender_value_snapshot, shipping_profile_id, shipping_profile_name_snapshot, shipping_profile_mode_snapshot, media_uuid_snapshot, quantity, unit_price_cents, shipping_price_cents, shipping_profile_max_cents, line_total_cents, shipping_total_cents)
                 VALUES
                    (:order_id, :artwork_id, :variant_id, :title, :variant_label, :size_value, :gender_value, :shipping_profile_id, :shipping_profile_name, :shipping_profile_mode, :media_uuid, :quantity, :unit_price, :shipping_price, :shipping_profile_max_cents, :line_total, :shipping_total)'
            );
            $reservationStmt = $this->pdo->prepare(
                'INSERT INTO sales_inventory_reservations
                    (tenant_id, artwork_id, variant_id, cart_id, order_id, quantity, status, expires_at)
                 VALUES
                    (:tenant_id, :artwork_id, :variant_id, :cart_id, :order_id, :quantity, "reserved", DATE_ADD(UTC_TIMESTAMP(), INTERVAL :minutes MINUTE))'
            );
            foreach ($items as $item) {
                $locked = $lockedItems[(int) $item['id']];
                $quantity = (int) $locked['quantity'];
                $lineTotal = $quantity * (int) $item['unit_price_cents'];
                $lineShipping = (int) ($shippingAllocations[(int) $item['id']] ?? $this->lineShippingCents(['quantity' => $quantity] + $item));
                $itemStmt->execute([
                    'order_id' => $orderId,
                    'artwork_id' => (int) $locked['artwork_id'],
                    'variant_id' => (int) $locked['variant_id'],
                    'title' => (string) $item['title_snapshot'],
                    'variant_label' => $item['variant_label_snapshot'] ?? null,
                    'size_value' => $item['size_value_snapshot'] ?? null,
                    'gender_value' => $item['gender_value_snapshot'] ?? null,
                    'shipping_profile_id' => isset($item['shipping_profile_id']) ? (int) $item['shipping_profile_id'] : null,
                    'shipping_profile_name' => $item['shipping_profile_name_snapshot'] ?? null,
                    'shipping_profile_mode' => $item['shipping_profile_mode_snapshot'] ?? null,
                    'media_uuid' => $item['media_uuid_snapshot'] ?? null,
                    'quantity' => $quantity,
                    'unit_price' => (int) $item['unit_price_cents'],
                    'shipping_price' => max(0, (int) ($item['shipping_price_cents'] ?? 0)),
                    'shipping_profile_max_cents' => isset($item['shipping_profile_max_cents']) ? (int) $item['shipping_profile_max_cents'] : null,
                    'line_total' => $lineTotal,
                    'shipping_total' => $lineShipping,
                ]);
                $reservationStmt->execute([
                    'tenant_id' => $tenant->tenantId,
                    'artwork_id' => (int) $locked['artwork_id'],
                    'variant_id' => (int) $locked['variant_id'],
                    'cart_id' => (int) $cart['id'],
                    'order_id' => $orderId,
                    'quantity' => $quantity,
                    'minutes' => self::RESERVATION_MINUTES,
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $this->order($tenant, $orderId) ?? throw new RuntimeException('Order was not created.');
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column');
        $stmt->execute(['table' => $table, 'column' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function attachCheckoutSession(int $orderId, string $sessionId, string $url): void
    {
        $stmt = $this->pdo->prepare('UPDATE sales_orders SET stripe_checkout_session_id = :session_id, stripe_checkout_url = :url, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $stmt->execute(['id' => $orderId, 'session_id' => $sessionId, 'url' => $url]);
    }

    public function markCartCheckedOut(int $cartId): void
    {
        $stmt = $this->pdo->prepare('UPDATE sales_carts SET status = "checked_out", updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $stmt->execute(['id' => $cartId]);
    }

    /**
     * Return the newest active Stripe checkout attempt for a cart.
     *
     * A cart can have only one useful checkout_pending order at a time. This
     * lookup lets the browser checkout action resume the hosted Stripe Session
     * instead of throwing a dead-end "already in progress" error.
     *
     * @return array<string,mixed>|null
     */
    public function pendingCheckoutForCart(TenantContext $tenant, int $cartId): ?array
    {
        if ($cartId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT *
               FROM sales_orders
              WHERE tenant_id = :tenant_id
                AND cart_id = :cart_id
                AND payment_status = "checkout_pending"
              ORDER BY id DESC
              LIMIT 1'
        );
        $stmt->execute([
            'tenant_id' => $tenant->tenantId,
            'cart_id' => $cartId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function order(TenantContext $tenant, int $orderId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sales_orders WHERE tenant_id = :tenant_id AND id = :id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'id' => $orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function orderBySession(TenantContext $tenant, string $sessionId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sales_orders WHERE tenant_id = :tenant_id AND stripe_checkout_session_id = :session_id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'session_id' => $sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function orders(TenantContext $tenant, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sales_orders WHERE tenant_id = :tenant_id ORDER BY created_at DESC LIMIT ' . max(1, $limit));
        $stmt->execute(['tenant_id' => $tenant->tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function orderItems(int $orderId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sales_order_items WHERE order_id = :order_id ORDER BY id');
        $stmt->execute(['order_id' => $orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateWorkflow(TenantContext $tenant, int $orderId, string $status, array $shipping): void
    {
        $allowed = ['ordered', 'acknowledged', 'packed', 'shipped'];
        if (!in_array($status, $allowed, true)) {
            $status = 'ordered';
        }
        $stmt = $this->pdo->prepare('UPDATE sales_orders SET workflow_status = :status, shipping_carrier = :carrier, shipping_tracking_number = :tracking, shipping_tracking_url = :url, updated_at = CURRENT_TIMESTAMP WHERE tenant_id = :tenant_id AND id = :id');
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'id' => $orderId, 'status' => $status, 'carrier' => $shipping['carrier'] ?? null, 'tracking' => $shipping['tracking'] ?? null, 'url' => $shipping['url'] ?? null]);
    }

    /**
     * Marks a Stripe checkout order as paid and consumes its inventory reservations.
     * Repeated Stripe webhooks are idempotent.
     */
    public function markPaidByStripeSession(string $sessionId, ?string $paymentIntentId, ?string $customerEmail, ?string $customerName, ?array $shippingAddress): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM sales_orders WHERE stripe_checkout_session_id = :session_id LIMIT 1 FOR UPDATE');
            $stmt->execute(['session_id' => $sessionId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                $this->pdo->commit();
                return;
            }

            $cartId = (int) ($order['cart_id'] ?? 0);
            if (in_array((string) $order['payment_status'], ['paid', 'complete', 'succeeded'], true)) {
                // Idempotent webhook/success retries still consume the cart.
                // Older code could mark an order paid while leaving the cart
                // visible if cookie expiration failed during the browser round trip.
                if ($cartId > 0) {
                    $cart = $this->pdo->prepare('UPDATE sales_carts SET status = "checked_out", updated_at = CURRENT_TIMESTAMP WHERE id = :cart_id AND status = "active"');
                    $cart->execute(['cart_id' => $cartId]);
                }
                $this->pdo->commit();
                return;
            }

            $manualReviewNotes = [];
            $reservationStmt = $this->pdo->prepare(
                'SELECT * FROM sales_inventory_reservations
                 WHERE order_id = :order_id
                 ORDER BY id
                 FOR UPDATE'
            );
            $reservationStmt->execute(['order_id' => (int) $order['id']]);
            $reservations = $reservationStmt->fetchAll(PDO::FETCH_ASSOC);
            if ($reservations === []) {
                $manualReviewNotes[] = 'Stripe reported this order paid, but no inventory reservations were found. Inventory was not decremented automatically.';
            }

            $touchedArtworks = [];
            foreach ($reservations as $reservation) {
                $reservationId = (int) ($reservation['id'] ?? 0);
                $reservationStatus = (string) ($reservation['status'] ?? '');
                if ($reservationStatus === 'completed') {
                    continue;
                }
                if ($reservationStatus !== 'reserved') {
                    $manualReviewNotes[] = 'Stripe reported this order paid, but reservation #' . $reservationId . ' was already ' . $reservationStatus . '. Inventory was not decremented for that reservation.';
                    continue;
                }

                $variantId = (int) ($reservation['variant_id'] ?? 0);
                if ($variantId <= 0) {
                    $manualReviewNotes[] = 'Stripe reported this order paid, but reservation #' . $reservationId . ' is missing its sale variant. Inventory was not decremented for that reservation.';
                    continue;
                }

                $decrement = $this->pdo->prepare(
                    'UPDATE artwork_sale_variants
                        SET inventory_quantity = inventory_quantity - :decrement_quantity,
                            updated_at = CURRENT_TIMESTAMP
                      WHERE id = :variant_id
                        AND tenant_id = :tenant_id
                        AND artwork_id = :artwork_id
                        AND inventory_quantity >= :quantity'
                );
                $decrement->execute([
                    'decrement_quantity' => (int) $reservation['quantity'],
                    'quantity' => (int) $reservation['quantity'],
                    'variant_id' => $variantId,
                    'artwork_id' => (int) $reservation['artwork_id'],
                    'tenant_id' => (int) $reservation['tenant_id'],
                ]);
                if ($decrement->rowCount() !== 1) {
                    $manualReviewNotes[] = 'Stripe reported this order paid, but reserved variant inventory could not be decremented for reservation #' . $reservationId . '. Manual inventory review is required.';
                    continue;
                }

                $completeReservation = $this->pdo->prepare(
                    'UPDATE sales_inventory_reservations
                     SET status = "completed", completed_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
                     WHERE id = :id AND status = "reserved"'
                );
                $completeReservation->execute(['id' => $reservationId]);
                $touchedArtworks[(int) $reservation['tenant_id'] . ':' . (int) $reservation['artwork_id']] = [(int) $reservation['tenant_id'], (int) $reservation['artwork_id']];
            }

            foreach ($touchedArtworks as [$tenantId, $artworkId]) {
                $this->syncLegacyArtworkInventoryFromVariants($tenantId, $artworkId);
            }

            $shippingJson = $shippingAddress ? json_encode($shippingAddress, JSON_THROW_ON_ERROR) : null;
            $notes = (string) ($order['notes'] ?? '');
            if ($manualReviewNotes !== []) {
                $notes = trim($notes);
                $notes .= ($notes !== '' ? "\n\n" : '')
                    . '[' . gmdate('Y-m-d H:i:s') . ' UTC] Stripe paid reconciliation inventory review: '
                    . implode(' ', $manualReviewNotes);
            }

            $update = $this->pdo->prepare(
                'UPDATE sales_orders
                    SET payment_status = "paid",
                        stripe_payment_intent_id = :payment_intent,
                        customer_email = COALESCE(:email, customer_email),
                        customer_name = COALESCE(:name, customer_name),
                        shipping_address_json = COALESCE(:shipping, shipping_address_json),
                        notes = :notes,
                        updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id'
            );
            $update->execute([
                'id' => (int) $order['id'],
                'payment_intent' => $paymentIntentId,
                'email' => $customerEmail,
                'name' => $customerName,
                'shipping' => $shippingJson,
                'notes' => $notes !== '' ? $notes : null,
            ]);

            // A paid order consumes the source cart. Leaving the cart active
            // makes bought items reappear after Stripe returns the buyer.
            if ($cartId > 0) {
                $cart = $this->pdo->prepare('UPDATE sales_carts SET status = "checked_out", updated_at = CURRENT_TIMESTAMP WHERE id = :cart_id AND status = "active"');
                $cart->execute(['cart_id' => $cartId]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function releaseReservationsForOrder(int $orderId, string $paymentStatus = 'checkout_failed'): int
    {
        $this->pdo->beginTransaction();
        try {
            $release = $this->pdo->prepare(
                'UPDATE sales_inventory_reservations
                 SET status = "released", released_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
                 WHERE order_id = :order_id AND status = "reserved"'
            );
            $release->execute(['order_id' => $orderId]);
            $released = $release->rowCount();

            $order = $this->pdo->prepare(
                'UPDATE sales_orders
                 SET payment_status = :payment_status, updated_at = UTC_TIMESTAMP()
                 WHERE id = :order_id AND payment_status = "checkout_pending"'
            );
            $order->execute(['order_id' => $orderId, 'payment_status' => $paymentStatus]);
            $this->pdo->commit();
            return $released;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function releaseExpiredReservations(): int
    {
        $this->pdo->beginTransaction();
        try {
            $orders = $this->pdo->prepare(
                'UPDATE sales_orders o
                 SET o.payment_status = "checkout_expired", o.updated_at = UTC_TIMESTAMP()
                 WHERE o.payment_status = "checkout_pending"
                   AND EXISTS (
                       SELECT 1
                       FROM sales_inventory_reservations r
                       WHERE r.order_id = o.id
                         AND r.status = "reserved"
                         AND r.expires_at <= UTC_TIMESTAMP()
                   )'
            );
            $orders->execute();

            $expired = $this->pdo->prepare(
                'UPDATE sales_inventory_reservations
                 SET status = "expired", released_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
                 WHERE status = "reserved" AND expires_at <= UTC_TIMESTAMP()'
            );
            $expired->execute();
            $count = $expired->rowCount();
            $this->pdo->commit();
            return $count;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function expireReservationsWithinTransaction(): void
    {
        $expired = $this->pdo->prepare(
            'UPDATE sales_inventory_reservations
             SET status = "expired", released_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
             WHERE status = "reserved" AND expires_at <= UTC_TIMESTAMP()'
        );
        $expired->execute();
    }

    /**
     * Returns tenant-scoped aggregate sales metrics for dashboards and analytics.
     */
    public function tenantSalesSummary(TenantContext $tenant): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS order_count,
                    COALESCE(SUM(total_cents), 0) AS gross_cents,
                    COALESCE(SUM(commission_cents), 0) AS commission_cents,
                    COALESCE(SUM(credit_card_fee_cents), 0) AS credit_card_fee_cents,
                    COALESCE(SUM(seller_net_cents), 0) AS seller_net_cents,
                    COALESCE(AVG(total_cents), 0) AS average_order_cents
             FROM sales_orders
             WHERE tenant_id = :tenant_id AND payment_status IN ("paid", "complete", "succeeded")'
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $status = $this->pdo->prepare(
            'SELECT workflow_status, COUNT(*) AS order_count
             FROM sales_orders
             WHERE tenant_id = :tenant_id
             GROUP BY workflow_status
             ORDER BY workflow_status'
        );
        $status->execute(['tenant_id' => $tenant->tenantId]);

        return [
            'order_count' => (int) ($summary['order_count'] ?? 0),
            'gross_cents' => (int) ($summary['gross_cents'] ?? 0),
            'commission_cents' => (int) ($summary['commission_cents'] ?? 0),
            'credit_card_fee_cents' => (int) ($summary['credit_card_fee_cents'] ?? 0),
            'seller_net_cents' => (int) ($summary['seller_net_cents'] ?? 0),
            'average_order_cents' => (int) round((float) ($summary['average_order_cents'] ?? 0)),
            'workflow_counts' => $status->fetchAll(PDO::FETCH_KEY_PAIR),
        ];
    }

    /**
     * Returns recent paid sales by day for tenant analytics charts.
     */
    public function tenantSalesByDay(TenantContext $tenant, int $days = 30): array
    {
        $days = max(1, min(365, $days));
        $stmt = $this->pdo->prepare(
            'SELECT DATE(created_at) AS sale_day,
                    COUNT(*) AS order_count,
                    COALESCE(SUM(total_cents), 0) AS gross_cents,
                    COALESCE(SUM(commission_cents), 0) AS commission_cents,
                    COALESCE(SUM(credit_card_fee_cents), 0) AS credit_card_fee_cents,
                    COALESCE(SUM(seller_net_cents), 0) AS seller_net_cents
             FROM sales_orders
             WHERE tenant_id = :tenant_id
               AND payment_status IN ("paid", "complete", "succeeded")
               AND created_at >= DATE_SUB(CURRENT_DATE, INTERVAL ' . $days . ' DAY)
             GROUP BY DATE(created_at)
             ORDER BY sale_day DESC'
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Returns tenant best sellers using paid order items.
     */
    public function tenantBestSellers(TenantContext $tenant, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT oi.artwork_id,
                    oi.title_snapshot,
                    COALESCE(SUM(oi.quantity), 0) AS units_sold,
                    COALESCE(SUM(oi.line_total_cents), 0) AS gross_cents
             FROM sales_order_items oi
             JOIN sales_orders o ON o.id = oi.order_id
             WHERE o.tenant_id = :tenant_id AND o.payment_status IN ("paid", "complete", "succeeded")
             GROUP BY oi.artwork_id, oi.title_snapshot
             ORDER BY gross_cents DESC, units_sold DESC
             LIMIT ' . max(1, $limit)
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Returns platform-wide aggregate sales metrics for operator dashboards.
     */
    public function platformSalesSummary(): array
    {
        $summary = $this->pdo->query(
            'SELECT COUNT(*) AS order_count,
                    COUNT(DISTINCT tenant_id) AS tenant_count,
                    COALESCE(SUM(total_cents), 0) AS gross_cents,
                    COALESCE(SUM(commission_cents), 0) AS commission_cents,
                    COALESCE(SUM(credit_card_fee_cents), 0) AS credit_card_fee_cents,
                    COALESCE(SUM(seller_net_cents), 0) AS seller_net_cents,
                    COALESCE(AVG(total_cents), 0) AS average_order_cents
             FROM sales_orders
             WHERE payment_status IN ("paid", "complete", "succeeded")'
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $status = $this->pdo->query(
            'SELECT workflow_status, COUNT(*) AS order_count
             FROM sales_orders
             GROUP BY workflow_status
             ORDER BY workflow_status'
        );

        return [
            'order_count' => (int) ($summary['order_count'] ?? 0),
            'tenant_count' => (int) ($summary['tenant_count'] ?? 0),
            'gross_cents' => (int) ($summary['gross_cents'] ?? 0),
            'commission_cents' => (int) ($summary['commission_cents'] ?? 0),
            'credit_card_fee_cents' => (int) ($summary['credit_card_fee_cents'] ?? 0),
            'seller_net_cents' => (int) ($summary['seller_net_cents'] ?? 0),
            'average_order_cents' => (int) round((float) ($summary['average_order_cents'] ?? 0)),
            'workflow_counts' => $status ? $status->fetchAll(PDO::FETCH_KEY_PAIR) : [],
        ];
    }

    /**
     * Returns platform-wide paid sales by day.
     */
    public function platformSalesByDay(int $days = 30): array
    {
        $days = max(1, min(365, $days));
        $stmt = $this->pdo->query(
            'SELECT DATE(created_at) AS sale_day,
                    COUNT(*) AS order_count,
                    COALESCE(SUM(total_cents), 0) AS gross_cents,
                    COALESCE(SUM(commission_cents), 0) AS commission_cents,
                    COALESCE(SUM(credit_card_fee_cents), 0) AS credit_card_fee_cents,
                    COALESCE(SUM(seller_net_cents), 0) AS seller_net_cents
             FROM sales_orders
             WHERE payment_status IN ("paid", "complete", "succeeded")
               AND created_at >= DATE_SUB(CURRENT_DATE, INTERVAL ' . $days . ' DAY)
             GROUP BY DATE(created_at)
             ORDER BY sale_day DESC'
        );

        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Returns platform sales totals grouped by tenant.
     */
    public function platformSalesByTenant(int $limit = 50): array
    {
        $stmt = $this->pdo->query(
            'SELECT t.name AS tenant_name,
                    t.slug AS tenant_slug,
                    COUNT(o.id) AS order_count,
                    COALESCE(SUM(o.total_cents), 0) AS gross_cents,
                    COALESCE(SUM(o.commission_cents), 0) AS commission_cents,
                    COALESCE(SUM(o.credit_card_fee_cents), 0) AS credit_card_fee_cents,
                    COALESCE(SUM(o.seller_net_cents), 0) AS seller_net_cents
             FROM sales_orders o
             JOIN tenants t ON t.id = o.tenant_id
             WHERE o.payment_status IN ("paid", "complete", "succeeded")
             GROUP BY t.id, t.name, t.slug
             ORDER BY gross_cents DESC
             LIMIT ' . max(1, $limit)
        );

        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function platformOrders(int $limit = 200): array
    {
        $stmt = $this->pdo->query('SELECT o.*, t.name AS tenant_name, t.slug AS tenant_slug FROM sales_orders o JOIN tenants t ON t.id = o.tenant_id ORDER BY o.created_at DESC LIMIT ' . max(1, $limit));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * Return buyer-visible shipping allocations for a full cart.
     *
     * Cart and checkout screens must use the same grouped profile logic, or
     * two small products using the same flat profile will display as two
     * separate shipping charges even though checkout groups them correctly.
     *
     * @param list<array<string,mixed>> $items
     * @return array<int,int> map of cart item id to allocated shipping cents
     */
    public function cartShippingAllocations(array $items): array
    {
        return $this->shippingAllocations($items);
    }

    /**
     * Allocate shipping by shipping profile across the whole cart/order.
     *
     * Items sharing a profile receive one profile-level base charge, optional
     * additional item charges, and an optional cap. This is what lets ten
     * different sticker products ship for one $5 small-flat charge.
     *
     * @param list<array<string,mixed>> $items
     * @return array<int,int> map of cart item id to allocated shipping cents
     */
    private function shippingAllocations(array $items): array
    {
        $allocations = [];
        $groups = [];
        foreach ($items as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $profileId = (int) ($item['shipping_profile_id'] ?? 0);
            $mode = (string) ($item['shipping_profile_mode_snapshot'] ?? '');
            $key = $profileId > 0 ? 'profile-' . $profileId : 'legacy-' . $id;
            $groups[$key][] = $item + ['__cart_item_id' => $id, '__shipping_mode' => $mode];
        }

        foreach ($groups as $group) {
            $remaining = $this->profileShippingTotal($group);
            foreach ($group as $index => $item) {
                $id = (int) $item['__cart_item_id'];
                if ($index === array_key_last($group)) {
                    $allocations[$id] = max(0, $remaining);
                    break;
                }
                $line = min(max(0, $remaining), $this->lineShippingCents($item));
                $allocations[$id] = $line;
                $remaining -= $line;
            }
        }

        return $allocations;
    }

    /** @param list<array<string,mixed>> $items */
    private function profileShippingTotal(array $items): int
    {
        if ($items === []) {
            return 0;
        }
        $first = $items[0];
        $mode = (string) ($first['shipping_profile_mode_snapshot'] ?? $first['__shipping_mode'] ?? '');
        if ($mode === 'free') {
            return 0;
        }
        if ($mode === 'quote') {
            throw new RuntimeException('This cart contains an item that requires a shipping quote before checkout.');
        }

        $quantity = 0;
        foreach ($items as $item) {
            $quantity += max(1, (int) ($item['quantity'] ?? 1));
        }
        if ($quantity <= 0) {
            return 0;
        }

        $base = max(0, (int) ($first['shipping_price_cents'] ?? 0));
        $additional = max(0, (int) ($first['shipping_additional_item_cents'] ?? 0));
        $total = $base + max(0, $quantity - 1) * $additional;
        if (array_key_exists('shipping_profile_max_cents', $first) && $first['shipping_profile_max_cents'] !== null && $first['shipping_profile_max_cents'] !== '') {
            $total = min($total, max(0, (int) $first['shipping_profile_max_cents']));
        }

        return $total;
    }

    /** @param array<string,mixed> $item */
    private function lineShippingCents(array $item): int
    {
        $quantity = max(1, (int) ($item['quantity'] ?? 1));
        $firstItemCents = max(0, (int) ($item['shipping_price_cents'] ?? 0));
        $additionalItemCents = max(0, (int) ($item['shipping_additional_item_cents'] ?? 0));

        return $firstItemCents + max(0, $quantity - 1) * $additionalItemCents;
    }

}

// End of file.
