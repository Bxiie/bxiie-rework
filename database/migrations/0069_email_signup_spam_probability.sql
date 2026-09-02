-- Adds a heuristic spam-likelihood score for new email-list signups.
-- Populated at signup/import time only (EmailSignupRepository::upsert); existing
-- rows are left NULL rather than backfilled, since the scoring inputs (IP
-- velocity at the time of signup) cannot be reconstructed after the fact.
ALTER TABLE email_signups
    ADD COLUMN spam_probability TINYINT UNSIGNED NULL AFTER consent_status;

-- Supports SpamScoreService's same-IP signup-velocity check.
CREATE INDEX IF NOT EXISTS idx_email_signups_ip_created
    ON email_signups (tenant_id, ip_address, created_at);

-- End of file.
