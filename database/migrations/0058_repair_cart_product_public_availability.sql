-- Repair public shopping-cart product availability after variant rollout.
-- This migration intentionally avoids REGEXP so it runs on MariaDB
-- regex engines and SQL modes used by production hosts.
--
-- This migration is intentionally data-safe and forward-only. It backfills a
-- default active variant for for-sale artworks that have sale configuration
-- but no active variant rows, and it repairs common phase-rollout rows where
-- a configured product kept legacy artwork inventory but the sale variant was
-- left inactive or at zero quantity.

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
    c.tenant_id,
    c.artwork_id,
    NULL,
    'Default',
    NULL,
    'not_applicable',
    c.base_price_cents,
    c.shipping_price_cents,
    c.shipping_additional_item_cents,
    CASE WHEN c.sale_kind = 'one_off' THEN 1 ELSE GREATEST(COALESCE(a.inventory_quantity, 1), 1) END,
    100,
    CASE WHEN a.sale_status = 'for_sale' AND c.checkout_enabled = 1 THEN 1 ELSE 0 END,
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
FROM artwork_sale_config c
JOIN artworks a ON a.id = c.artwork_id AND a.tenant_id = c.tenant_id
WHERE NOT EXISTS (
    SELECT 1
    FROM artwork_sale_variants v
    WHERE v.tenant_id = c.tenant_id
      AND v.artwork_id = c.artwork_id
);

UPDATE artwork_sale_config c
JOIN artworks a ON a.id = c.artwork_id AND a.tenant_id = c.tenant_id
SET c.checkout_enabled = 1,
    c.updated_at = CURRENT_TIMESTAMP
WHERE a.sale_status = 'for_sale'
  AND c.checkout_enabled = 0
  AND (c.base_price_cents IS NOT NULL OR TRIM(COALESCE(a.price, '')) <> '')
  AND EXISTS (
      SELECT 1
      FROM artwork_sale_variants v
      WHERE v.tenant_id = c.tenant_id
        AND v.artwork_id = c.artwork_id
        AND v.inventory_quantity > 0
  );

UPDATE artwork_sale_variants v
JOIN artwork_sale_config c ON c.tenant_id = v.tenant_id AND c.artwork_id = v.artwork_id
JOIN artworks a ON a.id = v.artwork_id AND a.tenant_id = v.tenant_id
SET v.inventory_quantity = CASE WHEN c.sale_kind = 'one_off' THEN 1 ELSE GREATEST(COALESCE(a.inventory_quantity, 1), 1) END,
    v.updated_at = CURRENT_TIMESTAMP
WHERE a.sale_status = 'for_sale'
  AND c.sale_kind IN ('one_off', 'limited_quantity')
  AND v.variant_label = 'Default'
  AND v.inventory_quantity = 0
  AND COALESCE(a.inventory_quantity, 0) > 0;

UPDATE artwork_sale_variants v
JOIN artwork_sale_config c ON c.tenant_id = v.tenant_id AND c.artwork_id = v.artwork_id
JOIN artworks a ON a.id = v.artwork_id AND a.tenant_id = v.tenant_id
SET v.is_active = 1,
    v.updated_at = CURRENT_TIMESTAMP
WHERE a.sale_status = 'for_sale'
  AND c.checkout_enabled = 1
  AND v.inventory_quantity > 0
  AND NOT EXISTS (
      SELECT 1
      FROM (
          SELECT tenant_id, artwork_id
          FROM artwork_sale_variants
          WHERE is_active = 1
      ) active_variants
      WHERE active_variants.tenant_id = v.tenant_id
        AND active_variants.artwork_id = v.artwork_id
  );

-- End of file.
