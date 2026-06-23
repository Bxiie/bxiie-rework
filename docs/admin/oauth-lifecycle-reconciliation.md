# OAuth signup lifecycle reconciliation

Google and Facebook tenant signups converge on the same tenant registration service. The registration transaction queues the 6-hour welcome, 1-day feature deep dive, and weekly check-in messages.

To inspect and repair a tenant with missing onboarding messages:

```bash
php scripts/email/reconcile_tenant_lifecycle_emails.php \
  --tenant-slug=facebooktest \
  --dry-run=1

php scripts/email/reconcile_tenant_lifecycle_emails.php \
  --tenant-slug=facebooktest
```

The command is idempotent. Existing messages in any status are reported and are not duplicated. Missing overdue messages are queued for immediate worker pickup.

<!-- End of file. -->
