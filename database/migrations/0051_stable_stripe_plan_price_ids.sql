-- Adds stable Stripe Product/Price identifiers to ArtsFolio plans.
-- Paid plan billing must use stripe_monthly_price_id instead of dynamic
-- Checkout price_data so later subscription item mutations are deterministic.

ALTER TABLE plans
    ADD COLUMN IF NOT EXISTS stripe_product_id VARCHAR(255) NULL AFTER monthly_price_cents,
    ADD COLUMN IF NOT EXISTS stripe_monthly_price_id VARCHAR(255) NULL AFTER stripe_product_id,
    ADD COLUMN IF NOT EXISTS stripe_price_lookup_key VARCHAR(255) NULL AFTER stripe_monthly_price_id;

ALTER TABLE tenant_plan_assignments
    ADD COLUMN IF NOT EXISTS stripe_subscription_item_id VARCHAR(255) NULL AFTER stripe_subscription_id,
    ADD COLUMN IF NOT EXISTS stripe_pending_update_id VARCHAR(255) NULL AFTER stripe_subscription_item_id;

CREATE INDEX IF NOT EXISTS idx_plans_stripe_monthly_price_id
    ON plans (stripe_monthly_price_id);

CREATE INDEX IF NOT EXISTS idx_tenant_plan_assignments_stripe_item
    ON tenant_plan_assignments (stripe_subscription_item_id);

-- End of file.
