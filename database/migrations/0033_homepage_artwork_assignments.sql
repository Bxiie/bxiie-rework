-- Adds explicit home page artwork assignment/order support.

CREATE TABLE IF NOT EXISTS homepage_artwork_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    artwork_id BIGINT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_homepage_artwork (tenant_id, artwork_id),
    INDEX idx_homepage_artwork_order (tenant_id, sort_order),
    CONSTRAINT fk_homepage_artwork_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_homepage_artwork_artwork FOREIGN KEY (artwork_id) REFERENCES artworks(id)
);

-- End of file.
