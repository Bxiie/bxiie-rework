-- Adds paid-plan curation queues, tenant editor/user roles, and user messages.
INSERT INTO roles (scope, slug, name, description, created_at)
SELECT 'tenant', 'editor', 'Editor', 'Reviews curation queues and publishes tenant artwork.', CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE scope = 'tenant' AND slug = 'editor');

INSERT INTO roles (scope, slug, name, description, created_at)
SELECT 'tenant', 'user', 'User', 'Adds artwork to curation queues and receives editor replies.', CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE scope = 'tenant' AND slug = 'user');

ALTER TABLE plans ADD COLUMN IF NOT EXISTS curation_workflow_included TINYINT(1) NOT NULL DEFAULT 0 AFTER allow_sales;
UPDATE plans SET curation_workflow_included = CASE WHEN slug = 'free' THEN 0 ELSE 1 END;

CREATE TABLE IF NOT EXISTS curation_lists (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    editor_user_id BIGINT UNSIGNED NULL,
    name VARCHAR(160) NOT NULL,
    is_central TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_curation_list_central (tenant_id, is_central, editor_user_id),
    KEY idx_curation_lists_tenant_editor (tenant_id, editor_user_id),
    CONSTRAINT fk_curation_lists_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_curation_lists_editor FOREIGN KEY (editor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS curation_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    list_id BIGINT UNSIGNED NOT NULL,
    artwork_id BIGINT UNSIGNED NOT NULL,
    submitted_by_user_id BIGINT UNSIGNED NOT NULL,
    note TEXT NULL,
    status ENUM('queued','reviewing','published','declined','removed') NOT NULL DEFAULT 'queued',
    reviewed_by_user_id BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_curation_item_open (list_id, artwork_id, submitted_by_user_id, status),
    KEY idx_curation_items_tenant_status (tenant_id, status, created_at),
    CONSTRAINT fk_curation_items_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_curation_items_list FOREIGN KEY (list_id) REFERENCES curation_lists(id) ON DELETE CASCADE,
    CONSTRAINT fk_curation_items_artwork FOREIGN KEY (artwork_id) REFERENCES artworks(id) ON DELETE CASCADE,
    CONSTRAINT fk_curation_items_submitter FOREIGN KEY (submitted_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_curation_items_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    recipient_user_id BIGINT UNSIGNED NOT NULL,
    sender_user_id BIGINT UNSIGNED NULL,
    curation_item_id BIGINT UNSIGNED NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_messages_recipient (tenant_id, recipient_user_id, read_at, created_at),
    CONSTRAINT fk_user_messages_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_messages_recipient FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_messages_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_user_messages_curation FOREIGN KEY (curation_item_id) REFERENCES curation_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- End of file.
