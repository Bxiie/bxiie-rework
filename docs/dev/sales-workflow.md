
## Sales workflow fulfillment implementation

Tenant-admin fulfillment lives in `app/Http/Controllers/Tenant/Admin/SalesController.php` and persists state through `app/Tenant/Sales/SalesRepository.php`.

Default sales tables exclude no-sale checkout rows by filtering to paid/refunded Stripe statuses: `paid`, `complete`, `succeeded`, `partially_refunded`, and `refunded`. The `include_no_sales=1` query parameter restores support visibility for abandoned or unpaid records.

Shipping notifications are queued through `EmailOutboxRepository` with `template_key = sales.shipping_notification`. The durable audit columns are `sales_orders.shipping_email_sent_at` and `sales_orders.shipping_email_outbox_id`, added by `database/migrations/0063_sales_workflow_shipping_email.sql`.

Refunds use `StripeCheckoutService::refundPaymentIntent()` and persist local audit rows in `sales_order_refunds`. The tenant route `/admin/sales/refund` must stay in `scripts/test/fixtures/route_inventory.json` whenever route inventory is regenerated.

# End of file.

## Shipping address rendering

`App\Http\Controllers\Tenant\Admin\SalesController` normalizes `sales_orders.shipping_address_json` through `shippingAddressHtml()` and `normalizedShippingAddress()` before rendering the order review page. Do not print the raw Stripe JSON blob in admin UI paths.

<!-- End of sales shipping display update. -->

<!-- sales-shipping-contact-20260708 -->
## Local shipping contact capture

`database/migrations/0064_sales_order_shipping_contact.sql` adds `sales_carts.shipping_phone`, `sales_carts.shipping_address_json`, and `sales_orders.shipping_phone`. `Tenant\SalesController` renders and validates local shipping fields before Stripe Checkout for shippable carts. `SalesRepository::createOrderFromCart()` copies local cart contact details to `sales_orders` before attaching the Stripe Checkout Session. Stripe webhooks and success-page reconciliation enrich `shipping_address_json` with Stripe-provided ship-to name and phone when available.

# End of sales shipping contact developer documentation.

### Stripe Checkout shipping prefill

ArtsFolio collects buyer contact and shipping details before creating a Stripe Checkout Session. When those details are present on the local `sales_orders` row, `App\Tenant\Sales\StripeCheckoutService` creates a Stripe Customer with the same email/name/phone/shipping address, passes that Customer to Checkout, sets `customer_update[name|address|shipping]=auto`, and attaches the same shipping fields to `payment_intent_data[shipping]`. This avoids asking the buyer to retype shipping details at Stripe while keeping Stripe's hosted Checkout flow as the payment surface.

