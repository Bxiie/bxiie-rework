# Tenant directory settings subpage

`app/Http/Controllers/Tenant/Admin/SettingsController.php` owns the tenant Directory settings UI as the `directory` settings section.

The section persists these tenant setting keys:

- `platform_directory_opt_in`
- `platform_directory_thumbnail_artwork_id`
- `platform_directory_summary`

`SettingsController::directorySettingsContent()` renders the subpage. `SettingsController::validDirectoryArtworkId()` verifies that selected thumbnails are published tenant artworks with primary media before persisting the artwork ID.

`app/Http/Controllers/Tenant/Admin/DiscoverySettingsController.php` is retained for backward compatibility. Its old `/admin/directory` GET route redirects to `/admin/settings?section=directory`, and old POSTs redirect to the same settings subpage after saving.

`app/Http/View/TenantAdminNav.php` no longer renders a separate Directory top-level nav item. Directory is accessed from the Settings subpage navigation.

Static coverage lives in `scripts/test/tenant_directory_settings_subpage_static.php` and is run by `scripts/test/preflight.sh`.

# End of file.
