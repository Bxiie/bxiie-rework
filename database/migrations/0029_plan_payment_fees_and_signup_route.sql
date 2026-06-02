-- Adds plan-scoped payment processing fee settings and persisted sale economics.

ALTER TABLE plans
    ADD COLUMN IF NOT EXISTS credit_card_fee_basis_points INT UNSIGNED NOT NULL DEFAULT 290 AFTER allow_sales,
    ADD COLUMN IF NOT EXISTS credit_card_fixed_fee_cents INT UNSIGNED NOT NULL DEFAULT 30 AFTER credit_card_fee_basis_points;

ALTER TABLE sales_orders
    ADD COLUMN IF NOT EXISTS credit_card_fee_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER commission_cents,
    ADD COLUMN IF NOT EXISTS seller_net_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER credit_card_fee_cents;

UPDATE plans
SET credit_card_fee_basis_points = CASE WHEN credit_card_fee_basis_points = 0 THEN 290 ELSE credit_card_fee_basis_points END,
    credit_card_fixed_fee_cents = CASE WHEN credit_card_fixed_fee_cents = 0 THEN 30 ELSE credit_card_fixed_fee_cents END
WHERE allow_sales = 1;

UPDATE sales_orders
SET seller_net_cents = GREATEST(0, subtotal_cents - commission_cents - credit_card_fee_cents)
WHERE seller_net_cents = 0;

# End of file.
