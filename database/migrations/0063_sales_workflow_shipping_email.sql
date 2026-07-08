-- Adds buyer shipping-notification audit columns for tenant-admin sales workflow.

ALTER TABLE sales_orders
    ADD COLUMN IF NOT EXISTS shipping_email_sent_at TIMESTAMP NULL AFTER shipping_tracking_url,
    ADD COLUMN IF NOT EXISTS shipping_email_outbox_id BIGINT UNSIGNED NULL AFTER shipping_email_sent_at;

CREATE INDEX IF NOT EXISTS idx_sales_orders_shipping_email ON sales_orders (tenant_id, workflow_status, shipping_email_sent_at);

# End of file.
