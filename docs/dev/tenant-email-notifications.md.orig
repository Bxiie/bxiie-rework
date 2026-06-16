# Tenant Email Notifications

## Scope

Tenant admin notification emails can now be queued for:

```text
contact messages
email-list signups
```

## Recipient resolution

Notification services resolve the recipient in this order:

```text
1. tenant_settings.site_admin_email
2. ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL
3. info@artsfol.io
```

This prevents the public contact form or signup form from accepting a record and then dropping the notification because `site_admin_email` is blank.

## Components

```text
App\Tenant\Contact\ContactNotificationService
App\Tenant\Signup\SignupNotificationService
```

## Test and smoke recipients

Queue-producing smoke tests must not enqueue mail to `.example.test` recipients. Use the production-controlled mailbox instead:

```text
info@artsfol.io
```

Do not change account identity tests such as password-auth users unless the test actually queues email; those tests use fake identities and should not mutate the real `info@artsfol.io` account.

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/contact_email_notification_static.php
php scripts/test/tenant_notifications.php
php scripts/test/email_outbox_status.php
```

Process queued email dry-run:

```bash
EMAIL_DRIVER=dry_run php scripts/workers/email_run_once.php
```

<!-- End of file. -->
