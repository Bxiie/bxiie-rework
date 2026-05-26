# Tenant directory opt-in

Tenant admins control public directory participation from `/admin/directory` on the tenant domain.

## Storage contract

The tenant admin form and public platform directory must use the same keys in `tenant_settings`:

- `platform_directory_opt_in` - `1`, `true`, `yes`, or `on` means the tenant opted in.
- `platform_directory_summary` - short public text shown on directory cards.

The public directory joins:

- `tenants.name` for the public display name.
- `tenant_domains.hostname` for the tenant URL.
- active, primary `tenant_domains` rows when available.

This matters because the current schema does **not** have `tenants.display_name` or `tenant_domains.domain`. Queries using those old names fail and make the public directory look empty.

## Verification

```bash
php scripts/debug/check_directory_contract.php
```

Then confirm the opted-in tenant row has:

```text
directory_opt_in = 1
primary_hostname = bxiie.com
primary_domain_status = active
```

Finally open:

```text
https://artsfol.io/directory
```

<!-- End of file. -->
