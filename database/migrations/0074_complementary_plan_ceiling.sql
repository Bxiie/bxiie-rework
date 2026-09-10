-- Give each complementary tenant an explicit highest plan available without billing.
ALTER TABLE tenants
    ADD COLUMN IF NOT EXISTS complementary_max_plan_id BIGINT UNSIGNED NULL AFTER complementary;

-- Preserve the former all-plans waiver for existing complementary tenants until
-- a platform administrator chooses a different ceiling.
UPDATE tenants
SET complementary_max_plan_id = (
    SELECT p.id
    FROM plans p
    WHERE p.is_active = 1
    ORDER BY p.display_order DESC, p.monthly_price_cents DESC, p.id DESC
    LIMIT 1
)
WHERE complementary = 1
  AND complementary_max_plan_id IS NULL;

-- End of file.
