-- Stabilizes pricing feature flags, complementary tenants, and cart abandonment email tracking.

ALTER TABLE plans
    ADD COLUMN IF NOT EXISTS allowed_storage_gb INT UNSIGNED NOT NULL DEFAULT 0 AFTER allowed_email_addresses,
    ADD COLUMN IF NOT EXISTS allowed_contact_messages INT UNSIGNED NOT NULL DEFAULT 0 AFTER allowed_storage_gb,
    ADD COLUMN IF NOT EXISTS allowed_admin_users INT UNSIGNED NOT NULL DEFAULT 1 AFTER allowed_contact_messages,
    ADD COLUMN IF NOT EXISTS allow_sales TINYINT(1) NOT NULL DEFAULT 0 AFTER custom_domain_included;

ALTER TABLE tenants
    ADD COLUMN IF NOT EXISTS complementary TINYINT(1) NOT NULL DEFAULT 0 AFTER status;

ALTER TABLE sales_carts
    ADD COLUMN IF NOT EXISTS customer_email VARCHAR(255) NULL AFTER status,
    ADD COLUMN IF NOT EXISTS customer_name VARCHAR(255) NULL AFTER customer_email,
    ADD COLUMN IF NOT EXISTS abandoned_12h_email_sent_at TIMESTAMP NULL AFTER updated_at,
    ADD COLUMN IF NOT EXISTS abandoned_24h_email_sent_at TIMESTAMP NULL AFTER abandoned_12h_email_sent_at;

UPDATE plans
SET
    allowed_storage_gb = CASE slug WHEN 'free' THEN 1 WHEN 'starter' THEN 1 WHEN 'studio' THEN 5 WHEN 'pro' THEN 25 WHEN 'collective' THEN 100 ELSE allowed_storage_gb END,
    allowed_contact_messages = CASE slug WHEN 'free' THEN 10 WHEN 'starter' THEN 10 WHEN 'studio' THEN 250 WHEN 'pro' THEN 1000 WHEN 'collective' THEN 5000 ELSE allowed_contact_messages END,
    allowed_admin_users = CASE slug WHEN 'free' THEN 1 WHEN 'starter' THEN 1 WHEN 'studio' THEN 3 WHEN 'pro' THEN 10 WHEN 'collective' THEN 25 ELSE allowed_admin_users END,
    allow_sales = CASE WHEN monthly_price_cents > 0 THEN 1 ELSE 0 END;

-- Preserve the Bxiie tenant as a paid-capable tenant when the plan assignment exists.
UPDATE tenant_settings ts
JOIN tenants t ON t.id = ts.tenant_id
SET ts.setting_value = 'studio', ts.updated_at = CURRENT_TIMESTAMP
WHERE t.slug = 'bxiie' AND ts.setting_key = 'billing_plan' AND ts.setting_value IN ('free', 'starter');

# End of file.
