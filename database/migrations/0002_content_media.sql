CREATE TABLE media_assets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    storage_path TEXT NOT NULL,
    mime_type VARCHAR(120) NULL,
    file_size_bytes BIGINT UNSIGNED NULL,
    width INT UNSIGNED NULL,
    height INT UNSIGNED NULL,
    alt_text TEXT NULL,
    title VARCHAR(255) NULL,
    caption TEXT NULL,
    credit TEXT NULL,
    is_private BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    INDEX idx_media_tenant (tenant_id),
    CONSTRAINT fk_media_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

CREATE TABLE artworks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    primary_media_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description MEDIUMTEXT NULL,
    medium VARCHAR(255) NULL,
    dimensions VARCHAR(255) NULL,
    year_created VARCHAR(50) NULL,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_artwork_slug (tenant_id, slug),
    INDEX idx_artworks_tenant_status (tenant_id, status),
    CONSTRAINT fk_artworks_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_artworks_primary_media FOREIGN KEY (primary_media_id) REFERENCES media_assets(id)
);

CREATE TABLE portfolio_sections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description MEDIUMTEXT NULL,
    show_as_tab BOOLEAN NOT NULL DEFAULT FALSE,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('active','hidden','archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_section_slug (tenant_id, slug),
    INDEX idx_sections_tenant (tenant_id),
    CONSTRAINT fk_sections_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

CREATE TABLE artwork_section_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    artwork_id BIGINT UNSIGNED NOT NULL,
    section_id BIGINT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_artwork_section (artwork_id, section_id),
    CONSTRAINT fk_artwork_section_artwork FOREIGN KEY (artwork_id) REFERENCES artworks(id),
    CONSTRAINT fk_artwork_section_section FOREIGN KEY (section_id) REFERENCES portfolio_sections(id)
);

CREATE TABLE pages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    body MEDIUMTEXT NULL,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_page_slug (tenant_id, slug),
    CONSTRAINT fk_pages_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

CREATE TABLE exhibitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    exhibition_date VARCHAR(120) NULL,
    name VARCHAR(255) NOT NULL,
    exhibition_type VARCHAR(255) NULL,
    location VARCHAR(255) NULL,
    city VARCHAR(120) NULL,
    state_region VARCHAR(120) NULL,
    work_name VARCHAR(255) NULL,
    notes MEDIUMTEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('active','hidden','archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    INDEX idx_exhibitions_tenant (tenant_id),
    CONSTRAINT fk_exhibitions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

CREATE TABLE newsletter_subscribers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(255) NULL,
    status ENUM('subscribed','unsubscribed','bounced','complained') NOT NULL DEFAULT 'subscribed',
    source VARCHAR(120) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_subscriber_email (tenant_id, email),
    CONSTRAINT fk_subscribers_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

CREATE TABLE contact_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    subject VARCHAR(255) NULL,
    message MEDIUMTEXT NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    status ENUM('new','read','archived','spam') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_contact_messages_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

CREATE TABLE analytics_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    path TEXT NULL,
    referrer TEXT NULL,
    ip_hash CHAR(64) NULL,
    user_agent TEXT NULL,
    country VARCHAR(120) NULL,
    region VARCHAR(120) NULL,
    city VARCHAR(120) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_analytics_tenant_created (tenant_id, created_at),
    INDEX idx_analytics_event_type (event_type),
    CONSTRAINT fk_analytics_events_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

CREATE TABLE analytics_rollups_daily (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    rollup_date DATE NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    event_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_daily_rollup (tenant_id, rollup_date, event_type),
    CONSTRAINT fk_analytics_rollups_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
