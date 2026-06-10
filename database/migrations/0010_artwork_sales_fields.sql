ALTER TABLE artworks
    ADD COLUMN IF NOT EXISTS sale_status ENUM('nfs', 'for_sale', 'sold') NOT NULL DEFAULT 'nfs' AFTER status,
    ADD COLUMN IF NOT EXISTS price VARCHAR(120) NULL AFTER sale_status;

-- End of file.
