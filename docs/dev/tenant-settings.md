# Tenant settings

## Runtime behavior

`App\Tenant\Settings\TenantSettingsRepository` bulk-loads every setting for a tenant into a request-local `TenantSettingsSnapshot` the first time any setting is requested.

Subsequent calls to either:

```php
$repository->get($tenant, 'setting_key', 'default');
$repository->snapshot($tenant)->get('setting_key', 'default');
```

read from memory and do not issue additional SQL queries.

The existing `all()` method remains available and returns the snapshot as an associative array.

## Saving settings

Use:

```php
$repository->set($tenant, 'setting_key', $value);
```

If the tenant snapshot has already been loaded in the current request, `set()` replaces it with a coherent updated snapshot. Code that modifies `tenant_settings` directly with SQL must call:

```php
$repository->invalidate($tenant);
```

before reading settings again through the repository in the same request.

## Why this exists

Public tenant pages read many appearance, navigation, typography, media, and commerce settings. Loading each key separately caused dozens of database queries per page. The snapshot reduces that work to one tenant-scoped settings query per request.

## Validation

Run:

```bash
php scripts/test/tenant_settings_snapshot_static.php
./scripts/test/preflight.sh
```

# End of file.
