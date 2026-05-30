# Contact Messages and Email Signups

## Scope

Tenant contact messages and email-list signups are now persisted before notification emails are queued.

## Tables

```text
contact_messages
email_signups
```

## Services

```text
App\Tenant\Contact\ContactMessageService
App\Tenant\Signup\EmailSignupService
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/database/migrate.php
php scripts/test/contact_signup_records.php
php scripts/test/email_outbox_status.php
```

## Notes

Contact/signup records are the source of truth.

Email notifications are secondary side effects queued into `email_outbox`.

<!-- End of file. -->
