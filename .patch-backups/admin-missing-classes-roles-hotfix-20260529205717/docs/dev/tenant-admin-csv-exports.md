# Tenant Admin CSV Exports

## Scope

Tenant admin list screens now include CSV exports.

## Routes

```text
GET /admin/contact-messages.csv
GET /admin/email-signups.csv
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
php scripts/test/csv_response.php
php scripts/test/contact_signup_records.php
php scripts/test/tenant_admin_lists.php
php -S 127.0.0.1:8080 -t public
```

Then visit tenant admin list screens and use export links.

<!-- End of file. -->
