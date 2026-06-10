-- Adds tenant email-list management fields and one-time cross-domain session bridge tickets.
-- This migration is additive and idempotent so it can be applied to existing tenant databases safely.

ALTER TABLE email_signups
    ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER source;

ALTER TABLE email_signups
    ADD INDEX IF NOT EXISTS idx_email_signups_search (tenant_id, email, name, source);

CREATE TABLE IF NOT EXISTS tenant_session_bridge_tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_hash CHAR(64) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    return_url TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    consumed_at TIMESTAMP NULL,
    INDEX idx_tenant_session_bridge_tickets_tenant_user (tenant_id, user_id),
    INDEX idx_tenant_session_bridge_tickets_expiry (expires_at),
    CONSTRAINT fk_tenant_session_bridge_tickets_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_tenant_session_bridge_tickets_user FOREIGN KEY (user_id) REFERENCES users(id)
);

-- End of file.
