# Billing Delinquency Audit

The billing delinquency audit is a read-only command for classifying past-due and unpaid tenants.

Run it after payment-failure incidents, webhook repairs, or billing deployments:

```bash
cd /var/www/artsfolio
php scripts/billing/audit_billing_delinquency.php
```

JSON output:

```bash
php scripts/billing/audit_billing_delinquency.php --json
```

Strict mode treats warnings as failures:

```bash
php scripts/billing/audit_billing_delinquency.php --strict
```

Policy thresholds:

- 0–6 days after `billing_action_required_at`: grace period
- 7–13 days: restriction threshold reached
- 14+ days: final review threshold reached
- 30 days is recorded as the long-tail review threshold in `BillingDelinquencyPolicy`

This pass does not restrict tenant features. It only classifies delinquency so enforcement can be wired later after observing real billing behavior.

Canonical policy lives in:

```text
app/Platform/Billing/BillingDelinquencyPolicy.php
```

<!-- End of file. -->

## Daily platform admin email report

The platform queues a daily billing delinquency report email to platform owner/admin users.

Command:

```bash
cd /var/www/artsfolio
php scripts/billing/send_billing_delinquency_report.php
```

Dry run:

```bash
php scripts/billing/send_billing_delinquency_report.php --dry-run
```

Force resend for the same UTC day:

```bash
php scripts/billing/send_billing_delinquency_report.php --force
```

Systemd units:

```text
scripts/systemd/artsfolio-billing-delinquency-report.service
scripts/systemd/artsfolio-billing-delinquency-report.timer
```

Install on production after deployment:

```bash
sudo cp /var/www/artsfolio/scripts/systemd/artsfolio-billing-delinquency-report.service /etc/systemd/system/
sudo cp /var/www/artsfolio/scripts/systemd/artsfolio-billing-delinquency-report.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now artsfolio-billing-delinquency-report.timer
sudo systemctl status artsfolio-billing-delinquency-report.timer --no-pager
```

Check recent runs:

```bash
sudo journalctl -u artsfolio-billing-delinquency-report.service -n 100 --no-pager
```

Outbox verification:

```sql
SELECT id, recipient_email, subject, template_key, status, available_at, sent_at, last_error
FROM email_outbox
WHERE template_key = 'billing.delinquency_daily_report'
ORDER BY id DESC
LIMIT 25;
```

<!-- End of file. -->
