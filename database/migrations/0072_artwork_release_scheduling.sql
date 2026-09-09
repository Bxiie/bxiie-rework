ALTER TABLE artworks
    ADD COLUMN scheduled_publish_at DATETIME NULL AFTER status,
    ADD INDEX idx_artworks_scheduled_publish (status, scheduled_publish_at);

CREATE TABLE artwork_release_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(191) NOT NULL,
    status ENUM('draft','scheduled','deployed','cancelled') NOT NULL DEFAULT 'draft',
    scheduled_at DATETIME NULL,
    deployed_at DATETIME NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_artwork_release_group_name (tenant_id, name),
    INDEX idx_artwork_release_groups_due (status, scheduled_at),
    CONSTRAINT fk_artwork_release_group_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_artwork_release_group_user FOREIGN KEY (created_by_user_id) REFERENCES users(id)
);

CREATE TABLE artwork_release_group_items (
    release_group_id BIGINT UNSIGNED NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL,
    artwork_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (release_group_id, artwork_id),
    UNIQUE KEY uq_artwork_release_membership (tenant_id, artwork_id),
    CONSTRAINT fk_artwork_release_item_group FOREIGN KEY (release_group_id) REFERENCES artwork_release_groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_artwork_release_item_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_artwork_release_item_artwork FOREIGN KEY (artwork_id) REFERENCES artworks(id) ON DELETE CASCADE
);

INSERT INTO background_jobs (tenant_id, job_type, payload, status, attempts, available_at, created_at)
SELECT NULL, 'artwork.publish_due', '{"interval_seconds":60}', 'queued', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
WHERE NOT EXISTS (
    SELECT 1 FROM background_jobs WHERE job_type = 'artwork.publish_due' AND status IN ('queued','running')
);
