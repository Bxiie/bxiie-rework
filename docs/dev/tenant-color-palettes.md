# Tenant color palettes

Tenant color/background palette presets are defined in:

```text
app/Http/Controllers/Tenant/Admin/SettingsController.php
```

The `palettes()` method returns the eight named palettes. The first entry must remain the new-site **Default** palette. `paletteButtons()` renders non-submit buttons with a JSON payload in `data-tenant-palette`.

Palette application is progressive JavaScript in:

```text
public/assets/admin-color-fields.js
```

The same script already enhances color text fields with color pickers and swatches. Palette clicks update existing form fields, dispatch `input` and `change` events, and therefore keep the enhanced picker/swatch UI in sync. The settings form still saves through the normal `SettingsController::update()` path.

Styles live in:

```text
public/assets/tenant-admin.css
```

Regression coverage lives in:

```text
scripts/test/tenant_color_palettes_static.php
```

and is called from:

```text
scripts/test/preflight.sh
```

When adding or changing palettes, update the static test and documentation at the same time.

Palette buttons are bound with delegated click handling so clicks on nested label, description, or swatch elements still apply the palette. Opacity inputs in `SettingsController::index()` must use `step="0.01"` so palette values such as `0.72`, `0.86`, and `0.03` pass browser validation.

Palette clicks update the visible settings-page preview immediately, including the top navigation bar, menu panel, and page surface variables. Save site settings is still required to persist the selected values.

Each palette also sets separate **Top bar text color** and **Menu text color** values. These keep the site title and navigation legible when a palette uses dark surfaces, such as Ink Studio. The palette buttons include a miniature preview showing the top bar, menu pill, and page/surface colors so the palette mood is visible before applying it.

# End of file.
