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
