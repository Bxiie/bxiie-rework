ALTER TABLE tenant_domains
    ADD COLUMN IF NOT EXISTS dns_last_checked_at TIMESTAMP NULL AFTER updated_at,
    ADD COLUMN IF NOT EXISTS dns_last_result JSON NULL AFTER dns_last_checked_at,
    ADD COLUMN IF NOT EXISTS dns_last_error TEXT NULL AFTER dns_last_result;
