-- Adds the phase-one cart variant catalog, cross-domain cart aliases, and shipping/abandonment columns.
-- This migration is deliberately backward-compatible with the current artwork-level cart code.

CREATE TABLE IF NOT EXISTS artwork_sale_config (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    artwork_id BIGINT UNSIGNED NOT NULL,
    sale_kind ENUM('one_off','limited_quantity','variant_inventory') NOT NULL DEFAULT 'one_off',
    option_schema ENUM('none','size_alpha','size_numeric','custom') NOT NULL DEFAULT 'none',
    gender_schema ENUM('none','mens','womens','unisex','selectable') NOT NULL DEFAULT 'none',
    base_price_cents INT UNSIGNED NULL,
    currency CHAR(3) NOT NULL DEFAULT 'usd',
    shipping_mode ENUM('none','flat_per_item','flat_per_order','variant') NOT NULL DEFAULT 'none',
    shipping_price_cents INT UNSIGNED NOT NULL DEFAULT 0,
    shipping_additional_item_cents INT UNSIGNED NOT NULL DEFAULT 0,
    ships_to_countries_json JSON NULL,
    checkout_enabled TINYINT(1) NOT NULL DEFAULT 0,
    require_shipping_address TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_artwork_sale_config_artwork (artwork_id),
    KEY idx_artwork_sale_config_tenant_enabled (tenant_id, checkout_enabled),
    CONSTRAINT fk_artwork_sale_config_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_artwork_sale_config_artwork FOREIGN KEY (artwork_id) REFERENCES artworks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS artwork_sale_variants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    artwork_id BIGINT UNSIGNED NOT NULL,
    sku VARCHAR(120) NULL,
    variant_label VARCHAR(255) NOT NULL,
    size_value VARCHAR(40) NULL,
    gender_value ENUM('mens','womens','unisex','not_applicable') NOT NULL DEFAULT 'not_applicable',
    price_cents INT UNSIGNED NULL,
    shipping_price_cents INT UNSIGNED NULL,
    shipping_additional_item_cents INT UNSIGNED NULL,
    inventory_quantity INT UNSIGNED NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_artwork_sale_variant_sku (tenant_id, sku),
    UNIQUE KEY uq_artwork_sale_variant_default (artwork_id, variant_label, size_value, gender_value),
    KEY idx_artwork_sale_variant_artwork_active (artwork_id, is_active, sort_order),
    KEY idx_artwork_sale_variant_tenant_artwork (tenant_id, artwork_id),
    CONSTRAINT fk_artwork_sale_variant_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_artwork_sale_variant_artwork FOREIGN KEY (artwork_id) REFERENCES artworks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_cart_aliases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    cart_id BIGINT UNSIGNED NOT NULL,
    cart_token_hash CHAR(64) NOT NULL,
    domain_host VARCHAR(255) NOT NULL,
    first_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sales_cart_alias_token (cart_token_hash),
    KEY idx_sales_cart_alias_tenant_cart (tenant_id, cart_id),
    KEY idx_sales_cart_alias_tenant_domain (tenant_id, domain_host),
    CONSTRAINT fk_sales_cart_alias_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_sales_cart_alias_cart FOREIGN KEY (cart_id) REFERENCES sales_carts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE sales_carts
    ADD COLUMN IF NOT EXISTS user_id BIGINT UNSIGNED NULL AFTER tenant_id,
    ADD COLUMN IF NOT EXISTS contact_email VARCHAR(255) NULL AFTER customer_name,
    ADD COLUMN IF NOT EXISTS cart_fingerprint CHAR(64) NULL AFTER cart_token,
    ADD COLUMN IF NOT EXISTS merged_into_cart_id BIGINT UNSIGNED NULL AFTER status,
    ADD COLUMN IF NOT EXISTS last_item_added_at TIMESTAMP NULL AFTER updated_at,
    ADD COLUMN IF NOT EXISTS abandoned_1d_email_sent_at TIMESTAMP NULL AFTER abandoned_24h_email_sent_at,
    ADD COLUMN IF NOT EXISTS abandoned_3d_email_sent_at TIMESTAMP NULL AFTER abandoned_1d_email_sent_at,
    ADD COLUMN IF NOT EXISTS abandoned_7d_email_sent_at TIMESTAMP NULL AFTER abandoned_3d_email_sent_at;

ALTER TABLE sales_cart_items
    ADD COLUMN IF NOT EXISTS variant_id BIGINT UNSIGNED NULL AFTER artwork_id,
    ADD COLUMN IF NOT EXISTS variant_label_snapshot VARCHAR(255) NULL AFTER title_snapshot,
    ADD COLUMN IF NOT EXISTS size_value_snapshot VARCHAR(40) NULL AFTER variant_label_snapshot,
    ADD COLUMN IF NOT EXISTS gender_value_snapshot VARCHAR(40) NULL AFTER size_value_snapshot,
    ADD COLUMN IF NOT EXISTS shipping_price_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER unit_price_cents,
    ADD COLUMN IF NOT EXISTS shipping_additional_item_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER shipping_price_cents;

ALTER TABLE sales_orders
    ADD COLUMN IF NOT EXISTS shipping_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER subtotal_cents;

ALTER TABLE sales_order_items
    ADD COLUMN IF NOT EXISTS variant_id BIGINT UNSIGNED NULL AFTER artwork_id,
    ADD COLUMN IF NOT EXISTS variant_label_snapshot VARCHAR(255) NULL AFTER title_snapshot,
    ADD COLUMN IF NOT EXISTS size_value_snapshot VARCHAR(40) NULL AFTER variant_label_snapshot,
    ADD COLUMN IF NOT EXISTS gender_value_snapshot VARCHAR(40) NULL AFTER size_value_snapshot,
    ADD COLUMN IF NOT EXISTS shipping_price_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER unit_price_cents,
    ADD COLUMN IF NOT EXISTS shipping_total_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER line_total_cents;

ALTER TABLE sales_inventory_reservations
    ADD COLUMN IF NOT EXISTS variant_id BIGINT UNSIGNED NULL AFTER artwork_id;

CREATE INDEX IF NOT EXISTS idx_sales_cart_items_variant ON sales_cart_items (variant_id);
CREATE INDEX IF NOT EXISTS idx_sales_order_items_variant ON sales_order_items (variant_id);
CREATE INDEX IF NOT EXISTS idx_sales_inventory_reservation_variant_status_expiry ON sales_inventory_reservations (variant_id, status, expires_at);
CREATE INDEX IF NOT EXISTS idx_sales_carts_known_owner ON sales_carts (tenant_id, status, contact_email, customer_email, updated_at);

INSERT INTO artwork_sale_config (
    tenant_id,
    artwork_id,
    sale_kind,
    option_schema,
    gender_schema,
    base_price_cents,
    currency,
    checkout_enabled,
    created_at,
    updated_at
)
SELECT
    a.tenant_id,
    a.id,
    CASE WHEN COALESCE(a.is_one_off, 1) = 1 THEN 'one_off' ELSE 'limited_quantity' END,
    'none',
    'none',
    CASE
        WHEN a.price IS NULL OR TRIM(a.price) = '' THEN NULL
        WHEN TRIM(a.price) REGEXP '^[[:space:]]*[$]?[0-9]+([,][0-9]{3})*([.][0-9]{1,2})?[[:space:]]*$'
            THEN CAST(ROUND(CAST(REPLACE(REPLACE(TRIM(a.price), '$', ''), ',', '') AS DECIMAL(12,2)) * 100) AS UNSIGNED)
        ELSE NULL
    END,
    'usd',
    CASE WHEN a.sale_status = 'for_sale' THEN 1 ELSE 0 END,
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
FROM artworks a
WHERE a.sale_status IN ('for_sale', 'sold')
   OR (a.price IS NOT NULL AND TRIM(a.price) <> '')
ON DUPLICATE KEY UPDATE
    sale_kind = VALUES(sale_kind),
    base_price_cents = COALESCE(artwork_sale_config.base_price_cents, VALUES(base_price_cents)),
    checkout_enabled = CASE WHEN VALUES(checkout_enabled) = 1 THEN 1 ELSE artwork_sale_config.checkout_enabled END,
    updated_at = CURRENT_TIMESTAMP;

INSERT INTO artwork_sale_variants (
    tenant_id,
    artwork_id,
    sku,
    variant_label,
    size_value,
    gender_value,
    price_cents,
    shipping_price_cents,
    shipping_additional_item_cents,
    inventory_quantity,
    sort_order,
    is_active,
    created_at,
    updated_at
)
SELECT
    a.tenant_id,
    a.id,
    NULL,
    'Default',
    NULL,
    'not_applicable',
    c.base_price_cents,
    NULL,
    NULL,
    GREATEST(1, COALESCE(NULLIF(a.inventory_quantity, 0), 1)),
    100,
    CASE WHEN a.sale_status = 'for_sale' THEN 1 ELSE 0 END,
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
FROM artworks a
JOIN artwork_sale_config c ON c.artwork_id = a.id
WHERE NOT EXISTS (
    SELECT 1
    FROM artwork_sale_variants v
    WHERE v.artwork_id = a.id
      AND v.variant_label = 'Default'
      AND v.gender_value = 'not_applicable'
);

UPDATE sales_cart_items ci
JOIN artwork_sale_variants v ON v.artwork_id = ci.artwork_id AND v.variant_label = 'Default' AND v.gender_value = 'not_applicable'
SET ci.variant_id = v.id,
    ci.variant_label_snapshot = COALESCE(ci.variant_label_snapshot, v.variant_label),
    ci.size_value_snapshot = COALESCE(ci.size_value_snapshot, v.size_value),
    ci.gender_value_snapshot = COALESCE(ci.gender_value_snapshot, v.gender_value)
WHERE ci.variant_id IS NULL;

UPDATE sales_order_items oi
JOIN artwork_sale_variants v ON v.artwork_id = oi.artwork_id AND v.variant_label = 'Default' AND v.gender_value = 'not_applicable'
SET oi.variant_id = v.id,
    oi.variant_label_snapshot = COALESCE(oi.variant_label_snapshot, v.variant_label),
    oi.size_value_snapshot = COALESCE(oi.size_value_snapshot, v.size_value),
    oi.gender_value_snapshot = COALESCE(oi.gender_value_snapshot, v.gender_value)
WHERE oi.variant_id IS NULL;

UPDATE sales_inventory_reservations r
JOIN artwork_sale_variants v ON v.artwork_id = r.artwork_id AND v.variant_label = 'Default' AND v.gender_value = 'not_applicable'
SET r.variant_id = v.id
WHERE r.variant_id IS NULL;

-- Keep uq_sales_cart_items_artwork and artwork-level reservation uniqueness until the runtime code is variant-aware.
-- Phase 3 should replace them after POST /cart/add always writes variant_id.

-- End of file.
