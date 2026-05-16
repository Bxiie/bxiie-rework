CREATE TABLE oauth_clients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NULL,
    client_name VARCHAR(255) NOT NULL,
    client_identifier VARCHAR(255) NOT NULL UNIQUE,
    client_secret_hash VARCHAR(255) NULL,
    client_type ENUM('confidential','public') NOT NULL DEFAULT 'confidential',
    redirect_uris JSON NOT NULL,
    allowed_grant_types JSON NOT NULL,
    allowed_scopes JSON NOT NULL,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    INDEX idx_oauth_clients_tenant (tenant_id),
    CONSTRAINT fk_oauth_clients_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

CREATE TABLE oauth_access_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token_hash CHAR(64) NOT NULL UNIQUE,
    client_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    tenant_id BIGINT UNSIGNED NULL,
    scopes JSON NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    revoked_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_oauth_access_tokens_user (user_id),
    INDEX idx_oauth_access_tokens_tenant (tenant_id),
    CONSTRAINT fk_oauth_access_tokens_client FOREIGN KEY (client_id) REFERENCES oauth_clients(id),
    CONSTRAINT fk_oauth_access_tokens_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_oauth_access_tokens_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

CREATE TABLE oauth_refresh_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token_hash CHAR(64) NOT NULL UNIQUE,
    access_token_id BIGINT UNSIGNED NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    tenant_id BIGINT UNSIGNED NULL,
    scopes JSON NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    revoked_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_oauth_refresh_tokens_user (user_id),
    CONSTRAINT fk_oauth_refresh_tokens_access FOREIGN KEY (access_token_id) REFERENCES oauth_access_tokens(id),
    CONSTRAINT fk_oauth_refresh_tokens_client FOREIGN KEY (client_id) REFERENCES oauth_clients(id),
    CONSTRAINT fk_oauth_refresh_tokens_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_oauth_refresh_tokens_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
