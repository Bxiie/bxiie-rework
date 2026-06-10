# Platform pricing and invite administration

Platform pricing is managed from `/platform/admin/pricing`. The editor updates existing rows in `plans`, can create new plan rows, and stores platform sales commission in `platform_settings.platform_sales_commission_basis_points`.

Tenant deletion is a soft delete. Platform admin forms require typing `delete`; deleted tenants are excluded from the default tenant list by `TenantAdminRepository::latest()`.

Platform and tenant user screens both support invite sending and resend. Invite email bodies are queued through `EmailOutboxRepository`, so SMTP delivery remains governed by platform email settings and the background worker.

The tenant public/admin menu surface uses `menu_background_enabled` and `menu_background_opacity`. When disabled or set to `0`, the CSS variables force a transparent nav panel and suppress the configured menu image.

# End of file.
