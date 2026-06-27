-- Adds subscription-billing state for tenant plan changes and Stripe recurring billing.

ALTER TABLE tenant_plan_assignments
    ADD COLUMN IF NOT EXISTS billing_status VARCHAR(40) NOT NULL DEFAULT 'manual' AFTER status,
    ADD COLUMN IF NOT EXISTS billing_interval_months INT UNSIGNED NOT NULL DEFAULT 1 AFTER billing_status,
    ADD COLUMN IF NOT EXISTS current_period_started_at DATETIME NULL AFTER billing_interval_months,
    ADD COLUMN IF NOT EXISTS current_period_ends_at DATETIME NULL AFTER current_period_started_at,
    ADD COLUMN IF NOT EXISTS pending_plan_id BIGINT UNSIGNED NULL AFTER current_period_ends_at,
    ADD COLUMN IF NOT EXISTS pending_plan_slug VARCHAR(80) NULL AFTER pending_plan_id,
    ADD COLUMN IF NOT EXISTS pending_change_type VARCHAR(40) NULL AFTER pending_plan_slug,
    ADD COLUMN IF NOT EXISTS pending_effective_at DATETIME NULL AFTER pending_change_type,
    ADD COLUMN IF NOT EXISTS pending_proration_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER pending_effective_at,
    ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(255) NULL AFTER pending_proration_cents,
    ADD COLUMN IF NOT EXISTS stripe_subscription_id VARCHAR(255) NULL AFTER stripe_customer_id,
    ADD COLUMN IF NOT EXISTS stripe_checkout_session_id VARCHAR(255) NULL AFTER stripe_subscription_id,
    ADD COLUMN IF NOT EXISTS stripe_payment_method_brand VARCHAR(80) NULL AFTER stripe_checkout_session_id,
    ADD COLUMN IF NOT EXISTS stripe_payment_method_last4 VARCHAR(8) NULL AFTER stripe_payment_method_brand,
    ADD COLUMN IF NOT EXISTS stripe_payment_method_exp_month INT UNSIGNED NULL AFTER stripe_payment_method_last4,
    ADD COLUMN IF NOT EXISTS stripe_payment_method_exp_year INT UNSIGNED NULL AFTER stripe_payment_method_exp_month,
    ADD COLUMN IF NOT EXISTS latest_invoice_id VARCHAR(255) NULL AFTER stripe_payment_method_exp_year,
    ADD COLUMN IF NOT EXISTS latest_charge_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER latest_invoice_id,
    ADD COLUMN IF NOT EXISTS latest_charge_at DATETIME NULL AFTER latest_charge_cents,
    ADD COLUMN IF NOT EXISTS cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0 AFTER latest_charge_at;

CREATE INDEX IF NOT EXISTS idx_tenant_plan_assignments_billing_status
    ON tenant_plan_assignments (billing_status);

CREATE INDEX IF NOT EXISTS idx_tenant_plan_assignments_current_period_ends_at
    ON tenant_plan_assignments (current_period_ends_at);

CREATE INDEX IF NOT EXISTS idx_tenant_plan_assignments_pending_effective_at
    ON tenant_plan_assignments (pending_effective_at);

CREATE INDEX IF NOT EXISTS idx_tenant_plan_assignments_stripe_subscription
    ON tenant_plan_assignments (stripe_subscription_id);

-- End of file.
