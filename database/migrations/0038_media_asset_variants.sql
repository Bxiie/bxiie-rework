-- Adds generated media variants so public grids can serve thumbnails instead of originals.

CREATE TABLE IF NOT EXISTS media_asset_variants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    media_asset_id BIGINT UNSIGNED NOT NULL,
    variant_key VARCHAR(32) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    width INT UNSIGNED NULL,
    height INT UNSIGNED NULL,
    file_size_bytes BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_media_asset_variant (media_asset_id, variant_key),
    INDEX idx_media_asset_variants_media_id (media_asset_id),
    CONSTRAINT fk_media_asset_variants_media_asset
        FOREIGN KEY (media_asset_id) REFERENCES media_assets(id)
        ON DELETE CASCADE
);

-- End of file.
