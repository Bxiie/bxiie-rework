# Billing Configuration

Platform Admin → Billing Configuration is a read-only Stripe setup validator.

It checks:

- `stripe_publishable_key` exists and starts with `pk_test_` or `pk_live_`
- `stripe_secret_key` exists and starts with `sk_test_` or `sk_live_`
- publishable and secret keys are both test mode or both live mode
- `stripe_webhook_secret` exists and starts with `whsec_`
- optional `stripe_billing_portal_configuration_id` is blank or starts with `bpc_`
- active paid plans have `stripe_monthly_price_id`
- active paid plan Price IDs start with `price_`
- active free plans do not have Stripe Price IDs
- `stripe_webhook_events` exists after migration 0053

Use this page before enabling live billing and after changing Stripe settings or plan prices.

The page masks secret values and does not modify settings.

<!-- End of file. -->
