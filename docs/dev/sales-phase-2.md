# Sales phase 2 developer notes

Sales phase 2 adds tenant-scoped carts, Stripe Checkout session creation, order records, order items, and webhook-driven payment completion.

Stripe implementation: ArtsFolio uses Stripe Checkout with destination charges when a tenant has `stripe_connected_account_id` in tenant settings. Platform settings provide `stripe_secret_key`, `stripe_publishable_key`, `stripe_webhook_secret`, and `platform_sales_commission_basis_points`. The checkout client posts directly to Stripe's form-encoded API so no Composer dependency is required.

Routes:
- `POST /cart/add`
- `GET /cart`
- `POST /cart/update`
- `POST /cart/checkout`
- `GET /checkout/success`
- `POST /stripe/webhook`
- `GET /admin/sales`
- `POST /admin/sales/update`
- `GET /platform/admin/sales`

Operational requirement: configure Stripe webhook endpoint to `https://artsfol.io/stripe/webhook` and subscribe at least to `checkout.session.completed`.

# End of file.
