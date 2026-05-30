# Tenant background image admin control

Tenant administrators can set a public site background image from `/admin/settings`.

## How it works

- Open `/admin/settings` on the tenant domain.
- In **Colors and background**, choose a **Background image**.
- Choose **Single image** or **Tile**.
- Set **Background tile size** when using tile mode, for example `240px`, `18rem`, or `25vw`.
- Set **Background opacity** from `0` through `1`.
- Save the settings and reload the public tenant site.

The picker intentionally lists only non-private media attached to published artwork. This keeps the public media route safe because background images are served through `/media?uuid=...`, which already requires published artwork visibility.

## Settings keys

- `background_media_uuid`: selected media UUID, or empty for no background image.
- `background_mode`: `single` or `tile`.
- `background_tile_size`: CSS size used for tiled backgrounds.
- `background_opacity`: CSS opacity from `0` through `1`.

## Operational notes

No schema migration is required. The existing `tenant_settings` key/value table stores the new `background_media_uuid` setting.

If no published artwork with media exists, the picker shows only `None`. Publish the artwork first, then return to `/admin/settings`.

<!-- End of file. -->
