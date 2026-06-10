# Directory thumbnails

Tenant admins choose the public directory image from **Tenant Admin → Directory**.

The selector only shows artworks that meet all of these conditions:

1. The artwork belongs to the current tenant.
2. The artwork status is `published`.
3. The artwork has a primary media asset.
4. The media asset is not private.

The selected artwork id is stored in `tenant_settings` using the key `platform_directory_thumbnail_artwork_id`.

Platform admins control whether the public directory is globally available from **Platform Admin → Platform Settings**. Tenant opt-in and tenant thumbnail selection remain tenant-owned controls.

# End of file.
