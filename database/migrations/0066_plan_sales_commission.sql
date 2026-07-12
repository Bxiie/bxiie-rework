-- Add per-plan ArtsFolio sales commission percentages.
ALTER TABLE plans
    ADD COLUMN IF NOT EXISTS platform_commission_basis_points INT UNSIGNED NOT NULL DEFAULT 500
        AFTER allow_sales;

-- Seed the launch economics while preserving custom plans at the prior 5% default.
UPDATE plans
SET platform_commission_basis_points = CASE slug
    WHEN 'free' THEN 1000
    WHEN 'studio' THEN 500
    WHEN 'pro' THEN 300
    WHEN 'collective' THEN 200
    ELSE COALESCE(platform_commission_basis_points, 500)
END;

-- Keep the old global setting as a compatibility fallback for pre-migration code.
INSERT INTO platform_settings (setting_key, setting_value, updated_at)
VALUES ('platform_sales_commission_basis_points', '500', CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE setting_value = setting_value;

-- End of file.
