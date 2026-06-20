# Tenant typography settings

Tenant Admin > Settings includes a Typography section for controlling public-site text on the home, portfolio, about, contact, artwork, forms, and footer areas.

The typography controls are convenience settings for the public tenant site. They save with the same full-page **Save site settings** buttons as the other settings sections.

## Available controls

Each tenant can choose font families for:

- Body text
- Headings
- Site title / brand
- Navigation
- Artwork titles
- Artwork metadata
- Forms
- Footer

Each tenant can also choose sizes with sliders and numeric pixel boxes for:

- Body text
- Main headings
- Subheadings
- Site title / brand
- Navigation
- Intro/prose text
- Artwork titles
- Artwork metadata
- Forms
- Footer

## Font picker behavior

The font picker uses curated local/system font stacks. This avoids external font downloads, tracking requests, privacy concerns, and slow page loads.

The picker includes a small preview sample below each select. The preview is an admin aid only; saved values take effect on public tenant pages after saving.

## Safe values

Font family values are restricted to the curated list in code.

Font size controls save pixel values such as `18px` or `72px`. Existing older `rem`, `em`, or `clamp(...)` values are converted to a reasonable pixel value when the settings page renders. Invalid or empty submitted values fall back to defaults.

## Where the settings are maintained

The settings UI and validation live in:

```text
app/Http/Controllers/Tenant/Admin/SettingsController.php
```

Public rendering variables are emitted from:

```text
app/Http/Controllers/Tenant/HomeController.php
```

Public CSS consumption lives in:

```text
public/assets/site.css
```

Admin control styling lives in:

```text
public/assets/tenant-admin.css
```

## Live preview and cache behavior

The font preview samples update immediately when a font picker, slider, or numeric size field changes. Public pages load `site.css` with a typography cache-bust query and emit a late tenant typography style block after `/tenant.css`, so saved font changes win over older tenant CSS and inline portfolio markup.

# End of file.
