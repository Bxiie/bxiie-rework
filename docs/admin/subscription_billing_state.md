# Billing State Audit

The billing state audit is a read-only command for detecting subscription state drift.

Run it after billing deployments, Stripe webhook changes, migration repairs, or payment incidents:

```bash
cd /var/www/artsfolio
php scripts/billing/audit_billing_state.php
```

Machine-readable output:

```bash
php scripts/billing/audit_billing_state.php --json
```

Strict mode treats warnings as failures:

```bash
php scripts/billing/audit_billing_state.php --strict
```

The auditor checks:

- unknown `tenant_plan_assignments.billing_status`
- unknown `tenant_plan_assignments.pending_change_type`
- pending changes missing `pending_effective_at`
- pending upgrades/downgrades missing `pending_plan_id`
- paid active/past-due/unpaid assignments missing `stripe_subscription_id`

Canonical statuses live in:

```text
app/Platform/Billing/BillingStatus.php
```

The auditor uses the normal application bootstrap and `config/database.php`, matching `scripts/database/migrate.php`.

<!-- End of file. -->
