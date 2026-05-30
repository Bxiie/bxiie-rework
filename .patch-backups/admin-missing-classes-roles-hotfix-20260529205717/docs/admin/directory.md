# Artist directory administration

The public directory is controlled by two switches.

1. A platform admin enables the global directory from Platform Admin → Settings.
2. A tenant admin enables the individual artist listing from Tenant Admin → Directory.

The tenant form writes `platform_directory_opt_in` and `platform_directory_summary` to `tenant_settings`. The public `/directory` page reads those same keys and only displays tenants whose status is `active` or `trial`.

Run this diagnostic from the project root when the public directory looks wrong:

```bash
php scripts/debug/check_directory_contract.php
```

The output shows the platform switch, the schema columns detected by the query, every tenant opt-in value, and whether each tenant is eligible for the public directory.

# End of file.
