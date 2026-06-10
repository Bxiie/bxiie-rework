ALTER TABLE artworks
    ADD COLUMN IF NOT EXISTS is_one_off BOOLEAN NOT NULL DEFAULT TRUE AFTER price,
    ADD COLUMN IF NOT EXISTS inventory_quantity INT UNSIGNED NOT NULL DEFAULT 1 AFTER is_one_off;

UPDATE artworks
SET inventory_quantity = 1
WHERE inventory_quantity IS NULL OR inventory_quantity = 0;

# End of file.
