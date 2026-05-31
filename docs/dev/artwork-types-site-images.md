# Artwork Types and Site Images

ArtsFolio separates public portfolio eligibility from site decoration eligibility with two seeded artwork types:

- `portfolio_images`: published artwork shown on home, portfolio, portfolio section, and artwork detail pages.
- `site_images`: published artwork available to tenant admin pickers for About, Contact, and Background images.

An artwork may have either type or both. Public portfolio queries require `portfolio_images`, so a site-only uploaded image can be published for picker use without becoming visible in portfolio browsing.

The schema is managed by `database/migrations/0015_artwork_types_site_images.sql`, which creates `artwork_types` and `artwork_type_assignments`, seeds both known types, and backfills existing non-archived artwork as `portfolio_images` to preserve current public behavior.

Operational checks:

```bash
php -l app/Tenant/Artwork/ArtworkReadRepository.php
php -l app/Http/Controllers/Tenant/Admin/ArtworksController.php
php -l app/Http/Controllers/Tenant/Admin/ArtworkUploadController.php
php -l app/Http/Controllers/Tenant/Admin/SettingsController.php
php -l app/Http/Controllers/Tenant/Admin/ContentController.php
php -l app/Http/Controllers/Tenant/HomeController.php
./scripts/test/preflight.sh
```

# End of file.
