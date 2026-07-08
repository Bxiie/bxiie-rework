# Stripe Connect commerce flow

Tenant artwork sales use Stripe Checkout with destination-charge Stripe Connect routing. The tenant stores `stripe_connected_account_id` plus non-secret readiness flags in `tenant_settings`.

## Files

- `app/Tenant/Sales/StripeConnectService.php` creates Express accounts, account links, and account status lookups.
- `app/Http/Controllers/Tenant/Admin/SettingsController.php` starts onboarding, handles Stripe return/refresh URLs, stores readiness flags, and renders the payout panel.
- `app/Http/Controllers/Tenant/SalesController.php` gates public checkout until the connected account is ready.
- `app/Tenant/Sales/StripeCheckoutService.php` creates Checkout Sessions with `payment_intent_data[transfer_data][destination]` and application fees.

## Runtime behavior

1. Artist clicks Settings, then Connect Stripe.
2. ArtsFolio creates an Express connected account if one does not already exist.
3. ArtsFolio creates a Stripe account onboarding link and redirects the artist to Stripe.
4. Stripe returns the artist to `/admin/settings/stripe/return`.
5. ArtsFolio retrieves the connected account and stores `charges_enabled`, `payouts_enabled`, and `details_submitted`.
6. Public checkout is allowed only when all readiness flags are true.

## Environment requirements

The platform Stripe secret key must be configured in platform settings. Stripe Connect must be enabled on the Stripe platform account. No tenant Stripe secret is stored.

<!-- End of file. -->
