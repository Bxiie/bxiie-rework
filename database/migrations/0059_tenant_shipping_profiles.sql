-- ArtsFolio shopping cart shipping profiles.
--
-- Shipping profiles let small/light items share a single profile-level charge
-- across multiple different cart items. This avoids charging, for example,
-- ten separate $5 shipping fees for ten different sticker products.

CREATE TABLE IF NOT EXISTS tenant_shipping_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    code VARCHAR(80) NOT NULL,
    mode ENUM('free','flat_profile','first_plus_additional','capped','per_item','quote') NOT NULL DEFAULT 'flat_profile',
    base_shipping_cents INT UNSIGNED NOT NULL DEFAULT 0,
    additional_item_cents INT UNSIGNED NOT NULL DEFAULT 0,
    max_shipping_cents INT UNSIGNED NULL,
    allow_checkout TINYINT(1) NOT NULL DEFAULT 1,
    buyer_label VARCHAR(255) NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT UNSIGNED NOT NULL DEFAULT 100,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_shipping_profile_code (tenant_id, code),
    KEY idx_tenant_shipping_profiles_tenant (tenant_id, sort_order, id),
    CONSTRAINT fk_tenant_shipping_profiles_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

INSERT INTO tenant_shipping_profiles
    (tenant_id, name, code, mode, base_shipping_cents, additional_item_cents, max_shipping_cents, allow_checkout, buyer_label, is_default, sort_order)
SELECT id, 'Small flat items', 'small_flat', 'flat_profile', 500, 0, 500, 1, 'Small flat items ship together for one flat charge.', 1, 100
FROM tenants
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO tenant_shipping_profiles
    (tenant_id, name, code, mode, base_shipping_cents, additional_item_cents, max_shipping_cents, allow_checkout, buyer_label, is_default, sort_order)
SELECT id, 'Small merchandise', 'small_merch', 'capped', 600, 200, 1400, 1, 'Small merchandise shipping is capped for combined orders.', 0, 110
FROM tenants
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO tenant_shipping_profiles
    (tenant_id, name, code, mode, base_shipping_cents, additional_item_cents, max_shipping_cents, allow_checkout, buyer_label, is_default, sort_order)
SELECT id, 'Free shipping', 'free_shipping', 'free', 0, 0, 0, 1, 'Shipping is included.', 0, 120
FROM tenants
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO tenant_shipping_profiles
    (tenant_id, name, code, mode, base_shipping_cents, additional_item_cents, max_shipping_cents, allow_checkout, buyer_label, is_default, sort_order)
SELECT id, 'Large artwork / quoted shipping', 'large_quote', 'quote', 0, 0, NULL, 0, 'Shipping is quoted by the artist before checkout.', 0, 130
FROM tenants
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

ALTER TABLE artwork_sale_config
    ADD COLUMN IF NOT EXISTS shipping_profile_id BIGINT UNSIGNED NULL AFTER shipping_mode;

ALTER TABLE artwork_sale_variants
    ADD COLUMN IF NOT EXISTS shipping_profile_id BIGINT UNSIGNED NULL AFTER gender_value;

ALTER TABLE sales_cart_items
    ADD COLUMN IF NOT EXISTS shipping_profile_id BIGINT UNSIGNED NULL AFTER gender_value_snapshot,
    ADD COLUMN IF NOT EXISTS shipping_profile_name_snapshot VARCHAR(120) NULL AFTER shipping_profile_id,
    ADD COLUMN IF NOT EXISTS shipping_profile_mode_snapshot VARCHAR(40) NULL AFTER shipping_profile_name_snapshot,
    ADD COLUMN IF NOT EXISTS shipping_profile_max_cents INT UNSIGNED NULL AFTER shipping_additional_item_cents;

ALTER TABLE sales_order_items
    ADD COLUMN IF NOT EXISTS shipping_profile_id BIGINT UNSIGNED NULL AFTER gender_value_snapshot,
    ADD COLUMN IF NOT EXISTS shipping_profile_name_snapshot VARCHAR(120) NULL AFTER shipping_profile_id,
    ADD COLUMN IF NOT EXISTS shipping_profile_mode_snapshot VARCHAR(40) NULL AFTER shipping_profile_name_snapshot,
    ADD COLUMN IF NOT EXISTS shipping_profile_max_cents INT UNSIGNED NULL AFTER shipping_price_cents;

UPDATE artwork_sale_config c
JOIN tenant_shipping_profiles p
  ON p.tenant_id = c.tenant_id
 AND p.code = CASE
    WHEN c.shipping_mode = 'none' THEN 'free_shipping'
    WHEN c.shipping_mode = 'flat_per_order' THEN 'small_flat'
    ELSE 'small_merch'
 END
SET c.shipping_profile_id = p.id,
    c.updated_at = CURRENT_TIMESTAMP
WHERE c.shipping_profile_id IS NULL;

UPDATE artwork_sale_variants v
JOIN artwork_sale_config c
  ON c.tenant_id = v.tenant_id
 AND c.artwork_id = v.artwork_id
SET v.shipping_profile_id = c.shipping_profile_id,
    v.updated_at = CURRENT_TIMESTAMP
WHERE v.shipping_profile_id IS NULL;

UPDATE sales_cart_items ci
JOIN artwork_sale_variants v
  ON v.id = ci.variant_id
JOIN tenant_shipping_profiles p
  ON p.id = COALESCE(v.shipping_profile_id, (
      SELECT c.shipping_profile_id
      FROM artwork_sale_config c
      WHERE c.tenant_id = v.tenant_id AND c.artwork_id = v.artwork_id
      LIMIT 1
  ))
SET ci.shipping_profile_id = p.id,
    ci.shipping_profile_name_snapshot = p.name,
    ci.shipping_profile_mode_snapshot = p.mode,
    ci.shipping_profile_max_cents = p.max_shipping_cents,
    ci.updated_at = CURRENT_TIMESTAMP
WHERE ci.shipping_profile_id IS NULL;

CREATE INDEX IF NOT EXISTS idx_artwork_sale_config_shipping_profile
    ON artwork_sale_config (shipping_profile_id);

CREATE INDEX IF NOT EXISTS idx_artwork_sale_variants_shipping_profile
    ON artwork_sale_variants (shipping_profile_id);

CREATE INDEX IF NOT EXISTS idx_sales_cart_items_shipping_profile
    ON sales_cart_items (cart_id, shipping_profile_id);

-- End of file.
