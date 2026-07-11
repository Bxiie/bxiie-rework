# Tenant admin layout

`App\Http\View\TenantAdminLayout` and `App\Http\View\TenantAdminNav` are the canonical tenant-admin presentation components.

Some older controllers still resolve `App\Http\Controllers\Tenant\Admin\AdminLayout`. That class is a compatibility facade which delegates to `App\Http\View\AdminLayout`; the latter detects tenant-host `/admin` requests and renders `TenantAdminLayout`.

Do not add tenant names, logos, colors, or navigation entries to the compatibility facade. Tenant identity comes from `TenantContext` and `TenantSettingsRepository`.

Directory is a Settings subpage and must not appear as a duplicate top-level item in `TenantAdminNav`.

Regression coverage:

- `scripts/test/tenant_admin_canonical_layout_static.php`
- `scripts/test/tenant_directory_settings_subpage_static.php`

<!-- End of file. -->
