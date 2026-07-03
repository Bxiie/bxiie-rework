-- Track original variant inventory so low-stock notices can be sent once when stock falls to 10%.

ALTER TABLE artwork_sale_variants
    ADD COLUMN IF NOT EXISTS original_inventory_quantity INT NULL AFTER inventory_quantity,
    ADD COLUMN IF NOT EXISTS low_stock_notified_at DATETIME NULL AFTER original_inventory_quantity;

UPDATE artwork_sale_variants
SET original_inventory_quantity = inventory_quantity
WHERE original_inventory_quantity IS NULL
  AND inventory_quantity IS NOT NULL;

-- EOF
