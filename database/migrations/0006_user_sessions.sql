CREATE TABLE user_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_hash CHAR(64) NOT NULL UNIQUE,
    user_id BIGINT UNSIGNED NOT NULL,
    tenant_id BIGINT UNSIGNED NULL,
    oauth_access_token_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    expires_at TIMESTAMP NOT NULL,
    revoked_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_sessions_user (user_id),
    INDEX idx_user_sessions_tenant (tenant_id),
    CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_user_sessions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
