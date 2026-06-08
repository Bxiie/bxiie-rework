-- Repairs contact-message and email-signup location columns used by the engagement repositories.
-- This migration is intentionally additive and idempotent because some databases were created
-- before location persistence was added while current code already writes these fields.

ALTER TABLE contact_messages
    ADD COLUMN IF NOT EXISTS country VARCHAR(120) NULL AFTER user_agent,
    ADD COLUMN IF NOT EXISTS region VARCHAR(120) NULL AFTER country,
    ADD COLUMN IF NOT EXISTS city VARCHAR(120) NULL AFTER region;

ALTER TABLE contact_messages
    ADD INDEX IF NOT EXISTS idx_contact_messages_location (tenant_id, country, region, city);

ALTER TABLE email_signups
    ADD COLUMN IF NOT EXISTS country VARCHAR(120) NULL AFTER user_agent,
    ADD COLUMN IF NOT EXISTS region VARCHAR(120) NULL AFTER country,
    ADD COLUMN IF NOT EXISTS city VARCHAR(120) NULL AFTER region;

ALTER TABLE email_signups
    ADD INDEX IF NOT EXISTS idx_email_signups_location (tenant_id, country, region, city);

-- End of file.
