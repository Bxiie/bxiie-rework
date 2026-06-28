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

## Billing email notifications

`App\Platform\Billing\BillingNotificationService` queues tenant-owner billing mail through `EmailOutboxRepository`.

Templates live under `template/email/billing`. Webhook handlers and scheduled billing scripts call the notification service after successful local billing state changes. Stripe webhook idempotency prevents duplicate event delivery from queuing duplicate webhook-driven billing emails.

<!-- End of file. -->

## Platform Admin billing configuration validation

`app/Http/Controllers/Platform/Admin/BillingConfigurationController.php` powers `/platform/admin/billing-configuration`.

The page is read-only. It validates Stripe key presence and format, live/test consistency, webhook secret format, optional Billing Portal configuration ID format, plan Price-ID readiness, and webhook event-log readiness. It masks secrets and checks `information_schema` before querying optional plan columns.

Use it before live billing cutover and after any Stripe plan or webhook changes.

<!-- End of file. -->

## Billing state vocabulary

`App\Platform\Billing\BillingStatus` contains the canonical subscription billing status and pending-change vocabulary.

Billing statuses:

- `active`
- `payment_pending`
- `past_due`
- `unpaid`
- `canceled`
- `cancellation_pending`

Pending change types:

- `upgrade`
- `downgrade`
- `cancel`

Use `scripts/billing/audit_billing_state.php` to detect database drift from these values. New controllers, webhook handlers, dashboards, and maintenance scripts should use `BillingStatus` constants and helpers instead of retyping literals.

The auditor uses the standard application bootstrap and `config/database.php`, matching `scripts/database/migrate.php`.

<!-- End of file. -->

## Billing delinquency policy

`App\Platform\Billing\BillingDelinquencyPolicy` defines read-only delinquency classification for failed subscription payments.

Current thresholds:

- `GRACE_DAYS = 7`
- `RESTRICTION_DAYS = 14`
- `FINAL_REVIEW_DAYS = 30`

`scripts/billing/audit_billing_delinquency.php` loads the normal application bootstrap and `config/database.php`, then lists tenants with `billing_status IN ('past_due', 'unpaid')` classified by policy state.

This pass intentionally does not restrict tenant access. Enforcement and feature gating should be added separately after operational review.

<!-- End of file. -->

## Daily billing delinquency report

`scripts/billing/send_billing_delinquency_report.php` sends a daily billing delinquency report to platform owner/admin users through `EmailOutboxRepository`.

The command uses `BillingDelinquencyPolicy`, renders `template/email/billing/platform-delinquency-report.txt`, and queues `billing.delinquency_daily_report`.

The command suppresses duplicate same-day reports unless `--force` is supplied. Use `--dry-run` for manual validation.

Production scheduling is handled by:

- `scripts/systemd/artsfolio-billing-delinquency-report.service`
- `scripts/systemd/artsfolio-billing-delinquency-report.timer`

<!-- End of file. -->

## Free-to-paid proration guard

`Tenant\Admin\BillingController::billablePaidUpgradeForProration()` is the gate for immediate proration.

Proration is allowed only when:

- current plan price is greater than zero
- target plan price is higher than the current plan price
- the tenant has a non-empty `stripe_subscription_id`
- billing status is blank or one of `active`, `past_due`, or `unpaid`

Free/no-subscription to paid plan starts must pass `0` proration cents into Stripe Checkout so the checkout page shows only the recurring target plan price.

<!-- End of file. -->

## Checkout entitlement activation guard

`Tenant\Admin\BillingController::recordPendingPaidPlanChange()` must keep `tenant_plan_assignments.plan_id` on the current plan while Stripe Checkout is pending. The target paid plan belongs in `pending_plan_id`.

Only `Platform\StripeWebhookController::markBillingCheckoutCompleted()` should activate the paid plan after Stripe confirms `checkout.session.completed`.

Use `scripts/billing/repair_unpaid_paid_start_entitlements.php --dry-run` to find rows where unpaid paid-start checkout accidentally activated the paid plan locally.

<!-- End of file. -->


## Broad unconfirmed paid entitlement repair

`scripts/billing/repair_unconfirmed_paid_entitlements.php` finds paid local entitlements that do not have confirmed Stripe subscription IDs. It is broader than `repair_unpaid_paid_start_entitlements.php` and is intended for checkout-cancel or malformed pending-state incidents.

The command defaults to rows with checkout/pending evidence and supports `--include-active` for explicit review of active paid rows without Stripe subscriptions.

<!-- End of file. -->
