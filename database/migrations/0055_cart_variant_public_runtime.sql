-- Switch cart rows to variant-level uniqueness for public sized/optioned shopping carts.
-- Phase 1 backfilled default variants, so phase 3 can make cart items variant-aware.

UPDATE sales_cart_items ci
JOIN artwork_sale_variants v ON v.artwork_id = ci.artwork_id AND v.variant_label = 'Default' AND v.gender_value = 'not_applicable'
SET ci.variant_id = v.id,
    ci.variant_label_snapshot = COALESCE(ci.variant_label_snapshot, v.variant_label),
    ci.size_value_snapshot = COALESCE(ci.size_value_snapshot, v.size_value),
    ci.gender_value_snapshot = COALESCE(ci.gender_value_snapshot, v.gender_value)
WHERE ci.variant_id IS NULL;

ALTER TABLE sales_cart_items
    MODIFY COLUMN variant_id BIGINT UNSIGNED NOT NULL;

-- Keep a plain cart_id index before dropping the old unique key. InnoDB may be using
-- uq_sales_cart_items_artwork as the supporting index for fk_sales_cart_items_cart.
CREATE INDEX IF NOT EXISTS idx_sales_cart_items_cart ON sales_cart_items (cart_id);

ALTER TABLE sales_cart_items
    DROP INDEX IF EXISTS uq_sales_cart_items_artwork;

CREATE UNIQUE INDEX IF NOT EXISTS uq_sales_cart_items_variant ON sales_cart_items (cart_id, artwork_id, variant_id);
CREATE INDEX IF NOT EXISTS idx_sales_cart_aliases_cart ON sales_cart_aliases (cart_id);

-- End of file.
