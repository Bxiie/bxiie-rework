-- Adds tenant-scoped shopping carts, Stripe checkout orders, and sales workflow tracking.

CREATE TABLE IF NOT EXISTS sales_carts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    cart_token CHAR(64) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sales_carts_token (cart_token),
    KEY idx_sales_carts_tenant_status (tenant_id, status),
    CONSTRAINT fk_sales_carts_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_cart_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cart_id BIGINT UNSIGNED NOT NULL,
    artwork_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price_cents INT UNSIGNED NOT NULL DEFAULT 0,
    title_snapshot VARCHAR(255) NOT NULL,
    media_uuid_snapshot CHAR(36) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sales_cart_items_artwork (cart_id, artwork_id),
    KEY idx_sales_cart_items_artwork (artwork_id),
    CONSTRAINT fk_sales_cart_items_cart FOREIGN KEY (cart_id) REFERENCES sales_carts(id) ON DELETE CASCADE,
    CONSTRAINT fk_sales_cart_items_artwork FOREIGN KEY (artwork_id) REFERENCES artworks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    cart_id BIGINT UNSIGNED NULL,
    order_number VARCHAR(40) NOT NULL,
    workflow_status VARCHAR(32) NOT NULL DEFAULT 'ordered',
    payment_status VARCHAR(32) NOT NULL DEFAULT 'checkout_pending',
    stripe_checkout_session_id VARCHAR(255) NULL,
    stripe_payment_intent_id VARCHAR(255) NULL,
    stripe_checkout_url TEXT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'usd',
    subtotal_cents INT UNSIGNED NOT NULL DEFAULT 0,
    commission_cents INT UNSIGNED NOT NULL DEFAULT 0,
    total_cents INT UNSIGNED NOT NULL DEFAULT 0,
    customer_name VARCHAR(255) NULL,
    customer_email VARCHAR(255) NULL,
    shipping_name VARCHAR(255) NULL,
    shipping_address_json JSON NULL,
    shipping_carrier VARCHAR(120) NULL,
    shipping_tracking_number VARCHAR(160) NULL,
    shipping_tracking_url TEXT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sales_orders_number (order_number),
    UNIQUE KEY uq_sales_orders_stripe_session (stripe_checkout_session_id),
    KEY idx_sales_orders_tenant_status (tenant_id, workflow_status, payment_status),
    CONSTRAINT fk_sales_orders_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_sales_orders_cart FOREIGN KEY (cart_id) REFERENCES sales_carts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    artwork_id BIGINT UNSIGNED NULL,
    title_snapshot VARCHAR(255) NOT NULL,
    media_uuid_snapshot CHAR(36) NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price_cents INT UNSIGNED NOT NULL DEFAULT 0,
    line_total_cents INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sales_order_items_order (order_id),
    KEY idx_sales_order_items_artwork (artwork_id),
    CONSTRAINT fk_sales_order_items_order FOREIGN KEY (order_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_sales_order_items_artwork FOREIGN KEY (artwork_id) REFERENCES artworks(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, updated_at)
SELECT id, 'stripe_connected_account_id', '', CURRENT_TIMESTAMP FROM tenants
ON DUPLICATE KEY UPDATE setting_value = setting_value;

# End of file.
