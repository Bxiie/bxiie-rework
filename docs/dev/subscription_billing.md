# Subscription Billing Workflow

Migration `0049_subscription_billing_workflow.sql` extends `tenant_plan_assignments`. Stripe subscription Checkout sessions are created by `App\Platform\Billing\StripeSubscriptionCheckoutService`.

<!-- End of file. -->

## Hardening pass

The hardening pass adds migration `0050_subscription_billing_hardening.sql`, additional webhook handling, and `scripts/billing/apply_pending_subscription_changes.php`.

The webhook records Stripe subscription state for `invoice.payment_failed`, `customer.subscription.updated`, and `customer.subscription.deleted`. The applicator processes local scheduled downgrades and cancellations whose `pending_effective_at` is due.

<!-- End of file. -->

## Stable Stripe Price IDs

Migration `0051_stable_stripe_plan_price_ids.sql` adds `plans.stripe_product_id`, `plans.stripe_monthly_price_id`, `plans.stripe_price_lookup_key`, and `tenant_plan_assignments.stripe_subscription_item_id`.

`StripeSubscriptionCheckoutService::createSubscriptionSession()` uses `line_items[0][price]` for the recurring subscription line. Dynamic recurring `price_data` must not be reintroduced because paid-to-paid subscription item updates need a stable target Price ID.

`customer.subscription.created` and `customer.subscription.updated` webhooks store the Stripe subscription item ID used by later subscription mutations.

<!-- End of file. -->

## Stripe Billing Portal pass

The portal pass adds `StripeBillingPortalService`, `POST /admin/billing/portal`, migration `0052_billing_portal_and_scheduler.sql`, and systemd units for scheduled billing changes.

The portal service creates Stripe Billing Portal sessions through `POST /v1/billing_portal/sessions`. The tenant controller records the last portal session ID and payment-method update request timestamp on the current tenant plan assignment.

The scheduled applicator should run hourly on production using `artsfolio-billing-scheduler.timer`.

<!-- End of file. -->

## Stripe webhook event logging

Migration `0053_stripe_webhook_event_log.sql` creates `stripe_webhook_events`.

`StripeWebhookController::receive()` records each Stripe event before it mutates billing or sales state. Duplicate processed events return `ok=true` with `duplicate=true` and do not re-run handlers. Failed events are marked `failed`, keep their last error, and may be retried by Stripe.

This table is the billing black-box recorder and should be checked during any payment incident.

<!-- End of file. -->

## Platform Admin billing health dashboard

`app/Http/Controllers/Platform/Admin/BillingHealthController.php` powers Platform Admin → Billing Health at `/platform/admin/billing-health`.

The controller is read-only and schema-tolerant. It checks `information_schema` before querying optional billing fields so it can diagnose partially applied billing migrations instead of failing on missing columns.

The dashboard should be reviewed after billing migrations, Stripe webhook configuration changes, failed payments, and scheduled billing-change deployments.

<!-- End of file. -->
