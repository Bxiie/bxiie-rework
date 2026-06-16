# Public Contact and Signup Routes

## Scope

Tenant public routes include POST handlers for:

```text
POST /contact
POST /signup
```

Platform public routes include:

```text
POST /contact
```

## GET forms

Tenant placeholder forms are rendered on:

```text
GET /contact
GET /
```

The platform contact form is rendered on:

```text
GET /contact
```

## Behavior

Tenant `POST /contact`:

```text
validates CSRF or configured human challenge
validates name/email/message
persists contact_messages row with tenant_id populated
queues tenant admin notification
```

Platform `POST /contact`:

```text
validates configured human challenge
validates name/email/message
persists contact_messages row with tenant_id = NULL
queues platform.contact_notification to email_outbox
```

Tenant `POST /signup`:

```text
validates CSRF or configured human challenge
validates email
persists or updates email_signups row
queues tenant admin notification
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/public_contact_signup_routes.sh
php scripts/test/platform_contact_management_static.php
php -S 127.0.0.1:8080 -t public
```

Inspect records after browser submission:

```bash
php scripts/test/contact_signup_records.php
php scripts/test/email_outbox_status.php
```

<!-- End of file. -->
