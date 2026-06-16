# Platform Contact Management Developer Notes

## Storage model

Platform contact submissions use the existing `contact_messages` table with `tenant_id = NULL`. Migration `0034_platform_contact_messages.sql` changes `contact_messages.tenant_id` from required to nullable and adds an index for platform contact status queries.

Tenant messages continue to use tenant-scoped rows with `tenant_id` populated.

## Code paths

```text
app/Http/Controllers/Platform/MarketingController.php
app/Platform/Contact/PlatformContactMessageRepository.php
app/Http/Controllers/Platform/Admin/ContactMessagesController.php
```

The marketing controller validates the public form, persists the message through `PlatformContactMessageRepository`, then queues a notification in `email_outbox`. The admin controller manages only rows where `tenant_id IS NULL`.

## Routes

```text
GET  /platform/admin/contacts
GET  /platform/admin/contacts.csv
POST /platform/admin/contacts/status
POST /platform/admin/contacts/delete
```

Legacy `/platform/admin/contact-messages` redirects to `/platform/admin/contacts`.

## Test coverage

Static regression coverage lives in:

```text
scripts/test/platform_contact_management_static.php
```

Run it with:

```bash
php scripts/test/platform_contact_management_static.php
```

<!-- End of file. -->
