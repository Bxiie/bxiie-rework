# Billing Health

Platform Admin → Billing Health is a read-only diagnostic dashboard for subscription billing.

It highlights:

- active paid plans missing `stripe_monthly_price_id`
- free plans that accidentally have Stripe Price IDs
- tenants stuck in `payment_pending`
- tenants marked `past_due`
- overdue scheduled downgrades or cancellations
- subscriptions missing `stripe_subscription_item_id`
- Stripe Billing Portal errors
- failed or stuck Stripe webhook events
- billing migration readiness

Use this page after billing deployments, Stripe webhook changes, failed-payment reports, and production migrations.

Common follow-up commands:

```bash
cd /var/www/artsfolio
php scripts/billing/apply_pending_subscription_changes.php --dry-run
sudo systemctl status artsfolio-billing-scheduler.timer
sudo journalctl -u artsfolio-billing-scheduler.service -n 100 --no-pager
```

Webhook diagnostics live in `stripe_webhook_events` after migration 0053.

<!-- End of file. -->
