# New-tenant Custom CSS

When a tenant is created, ArtsFolio copies the current
`public/assets/site.css` into the tenant's `tenant_css` setting.

`tenant_css` is the canonical key used by:

- Tenant Admin → Settings → Custom CSS;
- the public `/tenant.css` response;
- new-tenant provisioning.

Migration `0067_repair_new_tenant_css_setting_key.sql` repairs tenants created
while provisioning mistakenly wrote the snapshot under `custom_css`. It copies
the documented value only when `tenant_css` is absent or empty, so existing
tenant edits are never overwritten.

<!-- End of file. -->
