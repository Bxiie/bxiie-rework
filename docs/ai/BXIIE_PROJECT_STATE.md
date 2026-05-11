# Bxiie Project State

## Deployment model

All deployments must flow through Git from the development workstation repository:

```text
/Users/bxiie/Dropbox/artsy/site/bxiie_rework
```

The canonical remote is GitHub. Production pulls from GitHub using the configured deployment pull script.

## Current feature batch

This batch adds:

- Separate editable site title, browser title, artist name, and copyright name.
- Editable home intro text.
- About/contact image selection from admin site settings.
- Background image settings: selected image, single/tiled mode, tile size, and opacity.
- HTML-enabled admin content fields for trusted admin-entered content.
- Admin image editing.
- Admin event editing.
- Admin contact message viewer at `/admin/messages`.
- Usage stats with image thumbnails, image/name/location search, and location aggregation.
- City/state/country capture from proxy/geolocation headers when available.
- Newsletter modal after 60 seconds unless dismissed or subscribed.
- Public thumbnail year display and image detail year/medium display.

## Production migration

Run after deploying this batch:

```bash
sudo -u www-data DATABASE_PATH='/var/lib/bxiie-cms/database/bxiie.sqlite' STORAGE_PATH='/var/lib/bxiie-cms/storage' php scripts/migrate_feature_batch.php
```

# End of file.
