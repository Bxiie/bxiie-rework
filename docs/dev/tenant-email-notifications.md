# Tenant Email Notifications

## Scope

Tenant admin notification emails can now be queued for:

```text
contact messages
email-list signups
```

## Tenant setting

```text
site_admin_email
```

If `site_admin_email` is not set, notification services return `null` and no email is queued.

## Components

```text
App\Tenant\Contact\ContactNotificationService
App\Tenant\Signup\SignupNotificationService
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/tenant_notifications.php
php scripts/test/email_outbox_status.php
```

Process queued email dry-run:

```bash
EMAIL_DRIVER=dry_run php scripts/workers/email_run_once.php
```

<!-- End of file. -->
