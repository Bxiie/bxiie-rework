# Subscription Billing Administration

ArtsFolio bills paid tenant plans through Stripe Checkout subscriptions. Tenant owners must type `CHANGE PLAN` before plan changes. Paid starts and upgrades collect card details and bill immediately. Downgrades and cancellations take effect on the recurrence date.

<!-- End of file. -->

## Free-to-paid proration rule

Free, complimentary, and no-subscription tenants starting a paid plan should go through Stripe Checkout for the target monthly subscription price only.

Immediate proration belongs only to paid-to-paid upgrades where ArtsFolio already has an existing Stripe subscription ID. If a Free to Professional checkout shows an additional "Immediate prorated ArtsFolio plan change" line item, treat it as a billing bug.

<!-- End of file. -->

## Stripe Checkout entitlement activation

Paid plan entitlement must not activate when a tenant merely starts Stripe Checkout. ArtsFolio keeps the current plan in `tenant_plan_assignments.plan_id` and records the paid target in `pending_plan_id` until Stripe sends `checkout.session.completed`.

If a tenant cancels out of Stripe Checkout, the tenant should remain on the current plan with `billing_status = payment_pending` and a pending paid target.

Repair accidental local activation:

```bash
cd /var/www/artsfolio
php scripts/billing/repair_unpaid_paid_start_entitlements.php --dry-run
php scripts/billing/repair_unpaid_paid_start_entitlements.php --apply
```

Limit to one tenant by slug or ID:

```bash
php scripts/billing/repair_unpaid_paid_start_entitlements.php --dry-run --tenant=bxiie
```

<!-- End of file. -->


## Broad unconfirmed paid entitlement repair

If a tenant is locally on a paid plan but Stripe payment was never confirmed, use the broad repair command.

```bash
cd /var/www/artsfolio
php scripts/billing/repair_unconfirmed_paid_entitlements.php --dry-run --tenant=bxiie
php scripts/billing/repair_unconfirmed_paid_entitlements.php --apply --tenant=bxiie
```

By default, the command looks for paid local entitlements without `stripe_subscription_id` and with checkout/pending evidence. Use `--include-active` only when intentionally reviewing active paid rows that lack Stripe confirmation.

<!-- End of file. -->


## Canceled checkout entitlement repair

If a tenant canceled Stripe Checkout but the local active plan was changed to the paid target, repair the row with:

```bash
cd /var/www/artsfolio
php scripts/billing/repair_canceled_checkout_entitlement.php --dry-run --tenant=googlesignup
php scripts/billing/repair_canceled_checkout_entitlement.php --apply --tenant=googlesignup
```

The command restores `plan_id` to Free, preserves the paid target in `pending_plan_id`, normalizes `pending_change_type` to `paid_start`, and clears bad pending proration.

<!-- End of file. -->
