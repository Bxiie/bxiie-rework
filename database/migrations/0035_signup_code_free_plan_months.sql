-- Adds signup codes that grant free access to any selected plan for a fixed number of months.

ALTER TABLE tenant_signup_codes
    ADD COLUMN IF NOT EXISTS free_access_months INT UNSIGNED NOT NULL DEFAULT 0 AFTER max_redemptions;

ALTER TABLE tenant_plan_assignments
    ADD COLUMN IF NOT EXISTS complimentary_until DATETIME NULL AFTER status,
    ADD COLUMN IF NOT EXISTS granted_by_signup_code_id BIGINT UNSIGNED NULL AFTER complimentary_until,
    ADD COLUMN IF NOT EXISTS billing_note VARCHAR(255) NULL AFTER granted_by_signup_code_id;

CREATE INDEX IF NOT EXISTS idx_tenant_plan_assignments_complimentary_until
    ON tenant_plan_assignments (complimentary_until);

CREATE INDEX IF NOT EXISTS idx_tenant_plan_assignments_signup_code
    ON tenant_plan_assignments (granted_by_signup_code_id);

-- End of file.
