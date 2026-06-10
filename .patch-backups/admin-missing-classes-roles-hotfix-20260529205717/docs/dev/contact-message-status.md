# Contact Message Status

## Scope

Tenant admins can update contact message status.

## Route

```text
POST /admin/contact-messages/status
```

## Status values

```text
new
read
archived
spam
```

## Access

Requires one of:

```text
tenant owner
tenant admin
tenant editor
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/contact_message_status.php
php scripts/test/tenant_admin_lists.php
```

Browser route:

```text
/admin/contact-messages
```

<!-- End of file. -->
