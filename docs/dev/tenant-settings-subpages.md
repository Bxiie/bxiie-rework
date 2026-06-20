# Tenant settings subpages

`app/Http/Controllers/Tenant/Admin/SettingsController.php` renders Tenant Admin -> Settings as sectioned subpages behind the existing `/admin/settings` route.

The controller uses these helpers:

- `settingsSections()` defines the durable section keys, labels, and tooltip help.
- `activeSettingsSection()` normalizes `?section=` and hidden POST values.
- `settingsSubnav()` renders the subpage navigation.
- `settingsKeysForSection()` maps each subpage to the persisted setting keys it owns.

POST handling deliberately writes only the keys for the submitted subpage. This prevents saving `Typography`, `Colors & Backgrounds`, `Miscellaneous`, or `Custom CSS` from clearing fields that are now hidden on another subpage.

The existing route remains compatible:

```text
GET  /admin/settings
POST /admin/settings
```

Subpages use a query parameter rather than additional router entries:

```text
/admin/settings?section=identity
/admin/settings?section=typography
/admin/settings?section=colors-backgrounds
/admin/settings?section=miscellaneous
/admin/settings?section=custom-css
```

Static coverage lives in `scripts/test/tenant_settings_subpages_static.php` and is run by `scripts/test/preflight.sh`.

# End of file.
