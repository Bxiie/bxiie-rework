-- Adds tenant-admin Stripe refund records for sales orders.

CREATE TABLE IF NOT EXISTS sales_order_refunds (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    stripe_refund_id VARCHAR(255) NOT NULL,
    stripe_payment_intent_id VARCHAR(255) NOT NULL,
    amount_cents INT UNSIGNED NOT NULL DEFAULT 0,
    reason VARCHAR(64) NOT NULL DEFAULT 'requested_by_customer',
    status VARCHAR(64) NOT NULL DEFAULT 'unknown',
    restock_inventory TINYINT(1) NOT NULL DEFAULT 0,
    inventory_restored_at TIMESTAMP NULL,
    raw_json JSON NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sales_order_refunds_stripe_refund (stripe_refund_id),
    KEY idx_sales_order_refunds_order (order_id),
    KEY idx_sales_order_refunds_tenant_created (tenant_id, created_at),
    CONSTRAINT fk_sales_order_refunds_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_sales_order_refunds_order FOREIGN KEY (order_id) REFERENCES sales_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

# End of file.
