# Tenant Email Notifications Administration

## Current notifications

```text
contact form message notification
email-list signup notification
```

## Notification recipient order

Tenant public notifications are no longer silently dropped when a tenant admin email is blank. The recipient is resolved in this order:

```text
1. tenant_settings.site_admin_email
2. ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL
3. info@artsfol.io
```

Use the tenant admin settings page to set `site_admin_email` when an artist wants notifications to go directly to their own mailbox. Use `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL` when a deployment should route otherwise-unconfigured tenants somewhere other than `info@artsfol.io`.

## Production checks

After a contact form submission, confirm a row appears in both places:

```sql
SELECT id, tenant_id, email, subject, created_at
FROM contact_messages
ORDER BY id DESC
LIMIT 5;

SELECT id, status, recipient_email, subject, template_key, attempts, created_at, last_error
FROM email_outbox
ORDER BY id DESC
LIMIT 10;
```

Expected contact notification template key:

```text
tenant.contact_notification
```

## Operational reminder

The contact form queues mail. Delivery still requires the email worker/send path to be healthy.

```bash
systemctl status artsfolio-background-worker.service --no-pager
journalctl -u artsfolio-background-worker.service -n 100 --no-pager
```

<!-- End of file. -->
