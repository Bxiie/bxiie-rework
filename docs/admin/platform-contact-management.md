# Platform Contact Management

## Public submission path

The public platform contact form is available on the platform host:

```text
GET  /contact
POST /contact
```

`POST /contact` stores the submission in `contact_messages` with `tenant_id = NULL` and queues a notification email using `template_key = platform.contact_notification`.

## Admin workflow

Platform administrators manage public platform contact messages here:

```text
GET  /platform/admin/contacts
GET  /platform/admin/contacts.csv
POST /platform/admin/contacts/status
POST /platform/admin/contacts/delete
```

The Platform Admin sidebar contains a `Contacts` item. The page supports search, status filtering, sort, archive, hard delete, and CSV export. Status values are:

```text
new
read
archived
spam
```

Tenant contact messages are still managed from each tenant site at `/admin/contact-messages`. Platform contacts and tenant contacts share the table but are separated by `tenant_id`:

```text
tenant_id IS NULL      platform contact
tenant_id IS NOT NULL  tenant contact
```

## Notification destination

Platform contact notification email resolves in this order:

```text
ARTSFOLIO_PLATFORM_CONTACT_EMAIL
ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL
info@artsfol.io
```

Email notification is not the system of record. The stored `contact_messages` row is the managed application record.

<!-- End of file. -->
