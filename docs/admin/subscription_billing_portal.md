# Stripe Billing Portal and Billing Scheduler

Tenant owners can open Stripe Billing Portal from Tenant Admin → Billing to update card details, review invoices, and resolve failed-payment states.

The platform setting `stripe_billing_portal_configuration_id` is optional. Leave it blank to use the Stripe account default Billing Portal configuration. Set it to a `bpc_...` configuration ID when production needs a constrained portal experience.

Enable the scheduler on production after deployment:

```bash
sudo cp /var/www/artsfolio/scripts/systemd/artsfolio-billing-scheduler.service /etc/systemd/system/
sudo cp /var/www/artsfolio/scripts/systemd/artsfolio-billing-scheduler.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now artsfolio-billing-scheduler.timer
sudo systemctl status artsfolio-billing-scheduler.timer
```

Check recent runs:

```bash
sudo journalctl -u artsfolio-billing-scheduler.service -n 100 --no-pager
```

The service runs:

```bash
php /var/www/artsfolio/scripts/billing/apply_pending_subscription_changes.php --limit=250
```

Run a manual dry run before enabling the timer:

```bash
cd /var/www/artsfolio
php scripts/billing/apply_pending_subscription_changes.php --dry-run
```

<!-- End of file. -->
