-- Adds lifecycle and analytics fields needed by platform admin screens and API operations.

ALTER TABLE analytics_events
    ADD COLUMN IF NOT EXISTS ip_address varchar(64) NULL AFTER ip_hash;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS status varchar(32) NOT NULL DEFAULT 'active' AFTER display_name,
    ADD COLUMN IF NOT EXISTS suspended_at timestamp NULL AFTER status,
    ADD COLUMN IF NOT EXISTS deleted_at timestamp NULL AFTER suspended_at;

ALTER TABLE tenants
    ADD COLUMN IF NOT EXISTS suspended_at timestamp NULL AFTER status,
    ADD COLUMN IF NOT EXISTS deleted_at timestamp NULL AFTER suspended_at;

CREATE INDEX IF NOT EXISTS idx_analytics_events_ip_address
    ON analytics_events (ip_address, created_at);

CREATE INDEX IF NOT EXISTS idx_users_status
    ON users (status, deleted_at);

CREATE INDEX IF NOT EXISTS idx_tenants_status_lifecycle
    ON tenants (status, deleted_at);

-- End of file.
