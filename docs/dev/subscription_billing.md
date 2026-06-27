# Subscription Billing Workflow

Migration `0049_subscription_billing_workflow.sql` extends `tenant_plan_assignments`. Stripe subscription Checkout sessions are created by `App\Platform\Billing\StripeSubscriptionCheckoutService`.

<!-- End of file. -->

## Hardening pass

The hardening pass adds migration `0050_subscription_billing_hardening.sql`, additional webhook handling, and `scripts/billing/apply_pending_subscription_changes.php`.

The webhook records Stripe subscription state for `invoice.payment_failed`, `customer.subscription.updated`, and `customer.subscription.deleted`. The applicator processes local scheduled downgrades and cancellations whose `pending_effective_at` is due.

<!-- End of file. -->
