# Tenant Settings Save Buttons

Tenant Admin -> Settings renders a `Save site settings` button below each major section.

All buttons submit the same `/admin/settings` form with the same CSRF token and save all settings fields on the page. The repeated buttons are convenience controls only; they do not create section-scoped partial saves.

After using palette buttons or editing any individual field, a tenant admin may use the nearest `Save site settings` button to persist the full page.

# End of file.
