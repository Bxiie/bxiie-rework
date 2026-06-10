# Admin user/config/email repair - 2026-05-29

This update repairs admin logout CSRF handling, shows the signed-in user and role context in platform and tenant admin shells, and adds admin user-management screens.

## Platform admin

- `/platform/admin/users` lists platform-scoped users, roles, email address, display name, created timestamp, and last browser-session timestamp.
- `/platform/admin/users/password` rotates a platform user's local password. Passwords must be at least 12 characters.
- `/platform/admin/tenants/{id}` drills into tenant users for a selected tenant.
- `/platform/admin/tenants/users/password` rotates a tenant user's local password from the platform tenant drill-in screen.
- `/platform/admin/platform-settings` now exposes SMTP and Stripe/ecommerce configuration values stored in `platform_settings`.

## Tenant admin

- `/admin/users` lists tenant users, email address, display name, membership status, roles, created timestamp, and last browser-session timestamp.
- `/admin/users/password` rotates a tenant user's local password.
- Tenant settings include `site_admin_email`; contact-message and email-signup notifications are already queued through `email_outbox` when this setting is present.

## Security notes

The password screens do not edit role assignments. They only update the local `users.password_hash` for existing users in the relevant platform or tenant scope.

# End of file.
