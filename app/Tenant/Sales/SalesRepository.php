<?php

declare(strict_types=1);

namespace App\Tenant\Sales;

use App\Platform\Tenancy\TenantContext;
use PDO;
use RuntimeException;

/**
 * Persists tenant-scoped carts, orders, and sales workflow state.
 */
final class SalesRepository
{
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
        $stmt = $this->pdo->prepare('SELECT id, tenant_id, title, slug, media_uuid, sale_status, price, is_one_off, inventory_quantity FROM artworks WHERE tenant_id = :tenant_id AND id = :id AND status = "published" LIMIT 1');
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
            'max_quantity' => max(1, (int) ($artwork['inventory_quantity'] ?? 1)),
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

    public function createOrderFromCart(TenantContext $tenant, array $cart, array $items, int $commissionCents): array
    {
        if ($items === []) {
            throw new RuntimeException('Cart is empty.');
        }

        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += (int) $item['quantity'] * (int) $item['unit_price_cents'];
        }
        $orderNumber = 'AF-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('INSERT INTO sales_orders (tenant_id, cart_id, order_number, workflow_status, payment_status, currency, subtotal_cents, commission_cents, total_cents) VALUES (:tenant_id, :cart_id, :order_number, "ordered", "checkout_pending", "usd", :subtotal, :commission, :total)');
            $stmt->execute(['tenant_id' => $tenant->tenantId, 'cart_id' => (int) $cart['id'], 'order_number' => $orderNumber, 'subtotal' => $subtotal, 'commission' => $commissionCents, 'total' => $subtotal]);
            $orderId = (int) $this->pdo->lastInsertId();

            $itemStmt = $this->pdo->prepare('INSERT INTO sales_order_items (order_id, artwork_id, title_snapshot, media_uuid_snapshot, quantity, unit_price_cents, line_total_cents) VALUES (:order_id, :artwork_id, :title, :media_uuid, :quantity, :unit_price, :line_total)');
            foreach ($items as $item) {
                $itemStmt->execute([
                    'order_id' => $orderId,
                    'artwork_id' => (int) $item['artwork_id'],
                    'title' => (string) $item['title_snapshot'],
                    'media_uuid' => $item['media_uuid_snapshot'] ?? null,
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => (int) $item['unit_price_cents'],
                    'line_total' => (int) $item['quantity'] * (int) $item['unit_price_cents'],
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $this->order($tenant, $orderId) ?? throw new RuntimeException('Order was not created.');
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
     * Marks a Stripe checkout order as paid and decrements artwork inventory.
     */
    public function markPaidByStripeSession(string $sessionId, ?string $paymentIntentId, ?string $customerEmail, ?string $customerName, ?array $shippingAddress): void
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sales_orders WHERE stripe_checkout_session_id = :session_id LIMIT 1');
        $stmt->execute(['session_id' => $sessionId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            return;
        }

        $items = $this->orderItems((int) $order['id']);
        $this->pdo->beginTransaction();
        try {
            $update = $this->pdo->prepare('UPDATE sales_orders SET payment_status = "paid", stripe_payment_intent_id = :payment_intent, customer_email = :email, customer_name = :name, shipping_address_json = :shipping, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $update->execute(['id' => (int) $order['id'], 'payment_intent' => $paymentIntentId, 'email' => $customerEmail, 'name' => $customerName, 'shipping' => $shippingAddress ? json_encode($shippingAddress, JSON_THROW_ON_ERROR) : null]);

            foreach ($items as $item) {
                if (!empty($item['artwork_id'])) {
                    $decrement = $this->pdo->prepare('UPDATE artworks SET inventory_quantity = GREATEST(0, inventory_quantity - :quantity), sale_status = CASE WHEN is_one_off = 1 OR inventory_quantity <= :quantity THEN "sold" ELSE sale_status END, updated_at = CURRENT_TIMESTAMP WHERE id = :artwork_id');
                    $decrement->execute(['quantity' => (int) $item['quantity'], 'artwork_id' => (int) $item['artwork_id']]);
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
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
                    COALESCE(AVG(total_cents), 0) AS average_order_cents
             FROM sales_orders
             WHERE tenant_id = :tenant_id AND payment_status = "paid"'
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
                    COALESCE(SUM(commission_cents), 0) AS commission_cents
             FROM sales_orders
             WHERE tenant_id = :tenant_id
               AND payment_status = "paid"
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
             WHERE o.tenant_id = :tenant_id AND o.payment_status = "paid"
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
                    COALESCE(AVG(total_cents), 0) AS average_order_cents
             FROM sales_orders
             WHERE payment_status = "paid"'
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
            'average_order_cents' => (int) round((float) ($summary['average_order_cents'] ?? 0)),
            'workflow_counts' => $status->fetchAll(PDO::FETCH_KEY_PAIR),
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
                    COALESCE(SUM(commission_cents), 0) AS commission_cents
             FROM sales_orders
             WHERE payment_status = "paid"
               AND created_at >= DATE_SUB(CURRENT_DATE, INTERVAL ' . $days . ' DAY)
             GROUP BY DATE(created_at)
             ORDER BY sale_day DESC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                    COALESCE(SUM(o.commission_cents), 0) AS commission_cents
             FROM sales_orders o
             JOIN tenants t ON t.id = o.tenant_id
             WHERE o.payment_status = "paid"
             GROUP BY t.id, t.name, t.slug
             ORDER BY gross_cents DESC
             LIMIT ' . max(1, $limit)
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function platformOrders(int $limit = 200): array
    {
        $stmt = $this->pdo->query('SELECT o.*, t.name AS tenant_name, t.slug AS tenant_slug FROM sales_orders o JOIN tenants t ON t.id = o.tenant_id ORDER BY o.created_at DESC LIMIT ' . max(1, $limit));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// End of file.
