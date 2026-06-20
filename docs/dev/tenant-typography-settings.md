# Tenant typography settings

Tenant public typography is controlled through tenant settings persisted by `TenantSettingsRepository` and edited from Tenant Admin > Settings.

## Setting keys

Font-family keys:

```text
font_family_body
font_family_heading
font_family_brand
font_family_nav
font_family_artwork_title
font_family_artwork_meta
font_family_form
font_family_footer
```

Font-size keys:

```text
font_size_body
font_size_heading
font_size_subheading
font_size_brand
font_size_nav
font_size_prose
font_size_artwork_title
font_size_artwork_meta
font_size_form
font_size_footer
```

## Typography presets

Typography preset data is defined in `SettingsController::typographyPresets()`. Preset buttons are rendered by `SettingsController::typographyPresetButtons()` and use `data-typography-preset` JSON payloads. The JavaScript helper applies those values to the existing editable controls, syncs slider/number/hidden size fields, refreshes previews, and leaves saving to the normal settings form.

Presets currently include Clean Gallery, Editorial Serif, Museum Label, Poster Modern, Studio Notes, Bookish Warmth, and Slab Signal. Add new presets by using only fonts returned by `fontFamilyOptions()` and pixel values supported by the existing size controls.

## Admin UI

The Typography fieldset is rendered by:

```text
app/Http/Controllers/Tenant/Admin/SettingsController.php
```

The font picker is intentionally curated and local/system only. The application does not load external web fonts from the public tenant site.

Validation happens during settings save:

- `fontFamilyOptions()` provides the expanded local/system font list.
- `typographyPresets()` provides preset font/size combinations.
- `typographyPresetButtons()` renders preset buttons whose payloads fill the editable controls.
- `safeFontFamily()` restricts family values to the curated list.
- `fontSizeControl()` renders a range slider, numeric pixel field, and hidden submitted CSS value for each size. It does not render a separate size preview; JavaScript applies size changes to the matching font preview.
- `fontSizePixels()` converts older `rem`, `em`, and `clamp(...)` values to friendly pixel values for the UI.
- `safePublicFontSize()` still accepts conservative CSS values for backward compatibility, but the admin UI now submits pixel values.

## Public rendering

`HomeController::tenantTypographyCssVariables()` emits CSS variables into the public tenant `<body>` style attribute. `HomeController::tenantTypographyStyleBlock()` also emits computed literal font-family and font-size rules at the end of the public page, after `/tenant.css`, because tenant custom CSS and older generated markup can otherwise override the shared stylesheet or variable-only rules.

The public CSS applies variables to broad public-site groups:

- Body/prose text
- Main headings
- Subheadings
- Brand/site title
- Navigation
- Artwork card title and metadata
- Forms
- Footer/social links

The late inline typography block uses targeted `!important` rules for headings, portfolio card titles, forms, footer, and prose because older portfolio markup includes inline font-size declarations and `/tenant.css` is loaded after the shared stylesheet. This keeps admin typography settings authoritative without requiring a larger markup rewrite.

## Testing

Static coverage lives in:

```text
scripts/test/tenant_typography_settings_static.php
```

Preflight runs this test from:

```text
scripts/test/preflight.sh
```

## Live preview and cache behavior

The font preview samples update immediately when a font picker, typography preset, range slider, or numeric size field changes. Public pages load `site.css` with a typography cache-bust query and emit the late typography block after `/tenant.css`.

# End of file.
