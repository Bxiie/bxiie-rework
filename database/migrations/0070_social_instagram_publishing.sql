-- Adds provider-neutral social publishing persistence with Instagram as the first provider.
-- Social media credentials are encrypted in application code before storage.

ALTER TABLE artworks
    ADD COLUMN IF NOT EXISTS social_caption TEXT NULL AFTER notes_html,
    ADD COLUMN IF NOT EXISTS social_hashtags VARCHAR(1000) NULL AFTER social_caption;

CREATE TABLE IF NOT EXISTS social_connections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(40) NOT NULL,
    external_account_id VARCHAR(191) NOT NULL,
    username VARCHAR(191) NULL,
    token_ciphertext MEDIUMTEXT NOT NULL,
    token_expires_at DATETIME NULL,
    granted_scopes TEXT NULL,
    status ENUM('active','expired','revoked','error') NOT NULL DEFAULT 'active',
    last_error TEXT NULL,
    connected_by_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_social_connection_tenant_provider (tenant_id, provider),
    INDEX idx_social_connection_status (provider, status),
    CONSTRAINT fk_social_connection_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_social_connection_user FOREIGN KEY (connected_by_user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS social_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(40) NOT NULL DEFAULT 'instagram',
    name VARCHAR(191) NOT NULL,
    template_body MEDIUMTEXT NOT NULL,
    is_default BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_social_template_name (tenant_id, provider, name),
    INDEX idx_social_template_default (tenant_id, provider, is_default),
    CONSTRAINT fk_social_template_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

CREATE TABLE IF NOT EXISTS social_section_templates (
    tenant_id BIGINT UNSIGNED NOT NULL,
    section_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(40) NOT NULL DEFAULT 'instagram',
    template_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tenant_id, section_id, provider),
    CONSTRAINT fk_social_section_template_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_social_section_template_section FOREIGN KEY (section_id) REFERENCES portfolio_sections(id) ON DELETE CASCADE,
    CONSTRAINT fk_social_section_template_template FOREIGN KEY (template_id) REFERENCES social_templates(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tenant_user_permissions (
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    permission_key VARCHAR(120) NOT NULL,
    granted_by_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tenant_id, user_id, permission_key),
    CONSTRAINT fk_tenant_user_permission_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_tenant_user_permission_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_tenant_user_permission_granter FOREIGN KEY (granted_by_user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS social_posts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(40) NOT NULL DEFAULT 'instagram',
    source_artwork_id BIGINT UNSIGNED NULL,
    template_id BIGINT UNSIGNED NULL,
    caption MEDIUMTEXT NOT NULL,
    hashtags VARCHAR(2000) NULL,
    snapshot_json JSON NULL,
    status ENUM('draft','scheduled','publishing','published','failed','cancelled','authorization_required') NOT NULL DEFAULT 'draft',
    scheduled_at DATETIME NULL,
    next_attempt_at DATETIME NULL,
    publish_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    remote_post_id VARCHAR(191) NULL,
    remote_permalink TEXT NULL,
    last_error TEXT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    published_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_social_posts_due (status, provider, scheduled_at, next_attempt_at),
    INDEX idx_social_posts_tenant_created (tenant_id, created_at),
    CONSTRAINT fk_social_post_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_social_post_artwork FOREIGN KEY (source_artwork_id) REFERENCES artworks(id),
    CONSTRAINT fk_social_post_template FOREIGN KEY (template_id) REFERENCES social_templates(id),
    CONSTRAINT fk_social_post_created_user FOREIGN KEY (created_by_user_id) REFERENCES users(id),
    CONSTRAINT fk_social_post_updated_user FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS social_post_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    social_post_id BIGINT UNSIGNED NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL,
    artwork_id BIGINT UNSIGNED NOT NULL,
    media_asset_id BIGINT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    crop_mode ENUM('original','square','portrait','landscape') NOT NULL DEFAULT 'original',
    crop_json JSON NULL,
    derivative_path TEXT NULL,
    derivative_mime_type VARCHAR(120) NULL,
    remote_container_id VARCHAR(191) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_social_post_item_order (social_post_id, sort_order),
    INDEX idx_social_post_items_post (social_post_id, id),
    CONSTRAINT fk_social_post_item_post FOREIGN KEY (social_post_id) REFERENCES social_posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_social_post_item_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_social_post_item_artwork FOREIGN KEY (artwork_id) REFERENCES artworks(id),
    CONSTRAINT fk_social_post_item_media FOREIGN KEY (media_asset_id) REFERENCES media_assets(id)
);

CREATE TABLE IF NOT EXISTS social_publish_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    social_post_id BIGINT UNSIGNED NOT NULL,
    attempt_number INT UNSIGNED NOT NULL,
    status VARCHAR(40) NOT NULL,
    provider_code VARCHAR(120) NULL,
    message TEXT NULL,
    response_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_social_publish_attempt_post (social_post_id, attempt_number),
    CONSTRAINT fk_social_publish_attempt_post FOREIGN KEY (social_post_id) REFERENCES social_posts(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS social_media_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    social_post_item_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    storage_path TEXT NOT NULL,
    mime_type VARCHAR(120) NOT NULL DEFAULT 'image/jpeg',
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_social_media_token_expiry (expires_at),
    CONSTRAINT fk_social_media_token_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_social_media_token_item FOREIGN KEY (social_post_item_id) REFERENCES social_post_items(id) ON DELETE CASCADE
);

-- End of file.
