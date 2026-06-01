# Tenant admin surface preview

Tenant settings must render through `App\Http\View\TenantAdminLayout`, not the platform `AdminLayout`. The tenant layout exposes shared CSS variables for public tenant surfaces and tenant admin pages:

- `--bg`, `--primary`, `--accent`, `--text-color`
- `--topbar-bg-image`, `--topbar-bg-overlay`
- `--menu-bg-image`, `--menu-bg-overlay`
- `--heading-bg-overlay`, `--content-bg-overlay`, `--text-bg-overlay`
- `--artwork-card-bg-image`, `--artwork-card-bg-overlay`, `--artwork-card-bg-size`

`public/assets/site.css` owns public tenant rendering and contact form styling. `public/assets/tenant-admin.css` provides high-specificity admin overrides so tenant admin pages preview the same page settings without inheriting platform-admin chrome.

# End of file.
