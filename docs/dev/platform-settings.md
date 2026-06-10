# Platform Settings

## Scope

Platform-owned settings are separate from tenant/client settings.

## Table

```text
platform_settings
```

## Repository

```text
App\Platform\Settings\PlatformSettingsRepository
```

## Routes

```text
GET  /admin/platform-settings
POST /admin/platform-settings
```

On the platform host.

## Current editable settings

```text
platform_name
support_email
expected_ipv4
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/database/migrate.php
php scripts/test/platform_settings.php
```

## Boundary

Platform settings are managed by platform owner/admin roles.

Tenant/client settings remain under tenant admin.

<!-- End of file. -->
