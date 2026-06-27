-- Hardens subscription billing state after the Stripe subscription foundation.
-- Adds failed-payment, subscription-sync, and scheduled-change observability.
-- Safe to run after 0049_subscription_billing_workflow.sql.

ALTER TABLE tenant_plan_assignments
    ADD COLUMN IF NOT EXISTS stripe_subscription_status VARCHAR(64) NULL AFTER stripe_subscription_id,
    ADD COLUMN IF NOT EXISTS subscription_cancel_at DATETIME NULL AFTER cancel_at_period_end,
    ADD COLUMN IF NOT EXISTS last_payment_failed_at DATETIME NULL AFTER latest_charge_at,
    ADD COLUMN IF NOT EXISTS billing_action_required_at DATETIME NULL AFTER last_payment_failed_at,
    ADD COLUMN IF NOT EXISTS latest_invoice_url VARCHAR(1024) NULL AFTER latest_invoice_id,
    ADD COLUMN IF NOT EXISTS latest_invoice_number VARCHAR(255) NULL AFTER latest_invoice_url,
    ADD COLUMN IF NOT EXISTS pending_change_applied_at DATETIME NULL AFTER pending_effective_at,
    ADD COLUMN IF NOT EXISTS delinquency_email_sent_at DATETIME NULL AFTER billing_action_required_at;

CREATE INDEX IF NOT EXISTS idx_tenant_plan_assignments_billing_due
    ON tenant_plan_assignments (pending_change_type, pending_effective_at, pending_change_applied_at);

CREATE INDEX IF NOT EXISTS idx_tenant_plan_assignments_billing_status
    ON tenant_plan_assignments (billing_status, billing_action_required_at);

-- End of file.
