# Directory thumbnails

Tenant admins choose the public ArtsFolio directory thumbnail from **Admin → Directory**.

The selector only lists artworks that are:

1. owned by the current tenant,
2. published,
3. connected to a primary media image.

The selected artwork ID is stored in `tenant_settings.platform_directory_thumbnail_artwork_id`. The public directory reads that setting and renders the corresponding `media_assets.uuid` through the tenant domain using `/media?uuid=...`.

Run this check after changing directory settings:

```bash
php scripts/debug/check_directory_thumbnail_contract.php
```

# End of file.
