ALTER TABLE email_verification_tokens
    ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER user_id,
    ADD CONSTRAINT fk_email_verification_tokens_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    ADD INDEX idx_email_verification_tokens_user_tenant_active
        (user_id, tenant_id, consumed_at, expires_at);
