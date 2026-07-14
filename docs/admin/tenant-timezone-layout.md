# Tenant timezone page layout

`/account/timezone` is shared by platform and tenant hosts.

On a tenant host, the tenant route supplies `TenantContext` and
`TenantSettingsRepository` to `UserTimezoneController`. The controller renders
the page with `TenantAdminLayout`.

On the platform host, those optional dependencies are absent and the controller
uses `AdminLayout`.

This prevents tenant users from seeing the Platform Admin shell, navigation, or
identity banner when changing their personal timezone preference.

<!-- End of file. -->
