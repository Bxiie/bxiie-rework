-- Adds local buyer shipping contact capture before Stripe Checkout.

ALTER TABLE sales_carts
    ADD COLUMN IF NOT EXISTS shipping_phone VARCHAR(80) NULL AFTER customer_name,
    ADD COLUMN IF NOT EXISTS shipping_address_json JSON NULL AFTER shipping_phone;

ALTER TABLE sales_orders
    ADD COLUMN IF NOT EXISTS shipping_phone VARCHAR(80) NULL AFTER shipping_name;

# End of file.
