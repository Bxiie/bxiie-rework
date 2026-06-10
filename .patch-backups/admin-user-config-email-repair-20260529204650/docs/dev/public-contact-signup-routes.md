# Public Contact and Signup Routes

## Scope

Tenant public routes now include POST handlers for:

```text
POST /contact
POST /signup
```

## GET forms

Current placeholder forms are rendered on:

```text
GET /contact
GET /
```

## Behavior

`POST /contact`:

```text
validates CSRF
validates name/email/message
persists contact_messages row
queues tenant admin notification if site_admin_email is set
```

`POST /signup`:

```text
validates CSRF
validates email
persists or updates email_signups row
queues tenant admin notification if site_admin_email is set
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/public_contact_signup_routes.sh
php -S 127.0.0.1:8080 -t public
```

Inspect records after browser submission:

```bash
php scripts/test/contact_signup_records.php
php scripts/test/email_outbox_status.php
```

<!-- End of file. -->
