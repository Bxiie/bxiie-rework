# Tenant Settings Save Buttons

The tenant settings form is rendered by `app/Http/Controllers/Tenant/Admin/SettingsController.php`.

The controller defines one reusable `$saveButton` fragment and places it after each fieldset in the form. Each repeated button has `type="submit"` and submits the full settings form to `/admin/settings`; no JavaScript or section-specific persistence path is involved.

Styling lives in `public/assets/tenant-admin.css` under `.settings-section-actions`.

Regression coverage lives in `scripts/test/tenant_settings_section_save_static.php` and is invoked by `scripts/test/preflight.sh`.

# End of file.
