# Directory query contract

The public platform directory route is `GET /directory`.

The directory uses these durable settings:

- `platform_settings.platform_directory_enabled`: global platform switch. Missing value means enabled.
- `tenant_settings.platform_directory_opt_in`: tenant-level opt-in. Truthy values are `1`, `true`, `yes`, and `on`.
- `tenant_settings.platform_directory_summary`: optional public card summary.

The directory controller supports the current MariaDB schema and the older SQLite development schema by detecting these columns at runtime:

- `tenants.name` or `tenants.display_name`
- `tenant_domains.hostname` or `tenant_domains.domain`
- `tenant_settings` or `settings`

If the page shows no tenants, run:

```bash
php scripts/debug/check_directory_contract.php
```

Then check that the intended tenant has:

- status `active` or `trial`
- `directory_opt_in` equal to `1`, `true`, `yes`, or `on`
- a usable hostname, or a slug that can fall back to `{slug}.artsfol.io`

# End of file.
