# Subscription Billing Hardening

ArtsFolio should subscribe the Stripe webhook endpoint `/stripe/webhook` to these events:

- `checkout.session.completed`
- `invoice.paid`
- `invoice.payment_failed`
- `customer.subscription.updated`
- `customer.subscription.deleted`

`invoice.payment_failed` marks tenant billing as `past_due` and records that billing action is required.

`customer.subscription.updated` synchronizes Stripe subscription status, current period end, and cancellation-at-period-end state.

`customer.subscription.deleted` marks the local billing record as canceled.

Scheduled downgrades and cancellations are applied locally after their recurrence date by running:

```bash
php scripts/billing/apply_pending_subscription_changes.php
```

Use dry-run mode before enabling scheduled execution:

```bash
php scripts/billing/apply_pending_subscription_changes.php --dry-run
```

This script changes ArtsFolio feature access at the recorded recurrence boundary. Stripe remains the source of truth for actual payment collection and sends the webhook events above.

<!-- End of file. -->
