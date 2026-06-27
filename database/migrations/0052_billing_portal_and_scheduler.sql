-- Adds Stripe Billing Portal and payment-method update observability.
-- Safe to run after 0051_stable_stripe_plan_price_ids.sql.
--
-- Keep these ADD COLUMN statements order-agnostic. Production and development
-- may have slightly different tenant_plan_assignments helper columns depending
-- on which billing repair scripts were applied before this migration.

ALTER TABLE tenant_plan_assignments
    ADD COLUMN IF NOT EXISTS billing_portal_last_session_id VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS billing_portal_last_session_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS payment_method_update_requested_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS latest_stripe_error TEXT NULL;

CREATE INDEX IF NOT EXISTS idx_tenant_plan_assignments_portal_requested
    ON tenant_plan_assignments (payment_method_update_requested_at);

-- End of file.
