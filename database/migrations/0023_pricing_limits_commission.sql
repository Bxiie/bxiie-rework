-- Adds editable pricing limits and commission disclosure fields to platform plans.
ALTER TABLE plans
    ADD COLUMN IF NOT EXISTS description TEXT NULL AFTER monthly_price_cents,
    ADD COLUMN IF NOT EXISTS allowed_artworks INT UNSIGNED NULL AFTER custom_domain_included,
    ADD COLUMN IF NOT EXISTS allowed_email_addresses INT UNSIGNED NULL AFTER allowed_artworks,
    ADD COLUMN IF NOT EXISTS display_order INT UNSIGNED NOT NULL DEFAULT 100 AFTER allowed_email_addresses;

UPDATE plans
SET description = COALESCE(description, CASE slug
        WHEN 'free' THEN 'For evaluation, students, and artists publishing a compact first portfolio.'
        WHEN 'studio' THEN 'For working artists who need a polished site with practical admin tools and room to grow.'
        WHEN 'pro' THEN 'For artists who need their own domain and a more formal collector-facing presentation.'
        WHEN 'collective' THEN 'For galleries, artist groups, estates, and organizations managing more complex collections.'
        ELSE 'ArtsFolio artist portfolio plan.'
    END),
    allowed_artworks = COALESCE(allowed_artworks, CASE slug WHEN 'free' THEN 25 WHEN 'studio' THEN 250 WHEN 'pro' THEN 1000 WHEN 'collective' THEN 5000 ELSE 100 END),
    allowed_email_addresses = COALESCE(allowed_email_addresses, CASE slug WHEN 'free' THEN 100 WHEN 'studio' THEN 2500 WHEN 'pro' THEN 10000 WHEN 'collective' THEN 50000 ELSE 500 END),
    display_order = CASE slug WHEN 'free' THEN 10 WHEN 'studio' THEN 20 WHEN 'pro' THEN 30 WHEN 'collective' THEN 40 ELSE display_order END;

INSERT INTO platform_settings (setting_key, setting_value, updated_at)
VALUES ('platform_sales_commission_basis_points', '500', CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE setting_value = setting_value;

-- End of file.
