
## Sales workflow fulfillment implementation

Tenant-admin fulfillment lives in `app/Http/Controllers/Tenant/Admin/SalesController.php` and persists state through `app/Tenant/Sales/SalesRepository.php`.

Default sales tables exclude no-sale checkout rows by filtering to paid/refunded Stripe statuses: `paid`, `complete`, `succeeded`, `partially_refunded`, and `refunded`. The `include_no_sales=1` query parameter restores support visibility for abandoned or unpaid records.

Shipping notifications are queued through `EmailOutboxRepository` with `template_key = sales.shipping_notification`. The durable audit columns are `sales_orders.shipping_email_sent_at` and `sales_orders.shipping_email_outbox_id`, added by `database/migrations/0063_sales_workflow_shipping_email.sql`.

Refunds use `StripeCheckoutService::refundPaymentIntent()` and persist local audit rows in `sales_order_refunds`. The tenant route `/admin/sales/refund` must stay in `scripts/test/fixtures/route_inventory.json` whenever route inventory is regenerated.

# End of file.
