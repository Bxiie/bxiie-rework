-- Completes variant-aware checkout reservations and order snapshots.
-- Phase 4 moves checkout-time inventory safety from artwork-level rows to sale variants.

UPDATE sales_inventory_reservations r
JOIN artwork_sale_variants v
  ON v.artwork_id = r.artwork_id
 AND v.variant_label = 'Default'
 AND v.gender_value = 'not_applicable'
SET r.variant_id = v.id
WHERE r.variant_id IS NULL;

ALTER TABLE sales_inventory_reservations
    MODIFY COLUMN variant_id BIGINT UNSIGNED NOT NULL;

-- Keep a plain order_id index before dropping the old unique key. InnoDB may be using
-- uq_sales_inventory_reservation_order_artwork as the supporting index for the order FK.
CREATE INDEX IF NOT EXISTS idx_sales_inventory_reservation_order
    ON sales_inventory_reservations (order_id);

ALTER TABLE sales_inventory_reservations
    DROP INDEX IF EXISTS uq_sales_inventory_reservation_order_artwork;

CREATE UNIQUE INDEX IF NOT EXISTS uq_sales_inventory_reservation_order_variant
    ON sales_inventory_reservations (order_id, artwork_id, variant_id);

CREATE INDEX IF NOT EXISTS idx_sales_inventory_reservation_variant_order
    ON sales_inventory_reservations (variant_id, order_id, status);

-- End of file.
