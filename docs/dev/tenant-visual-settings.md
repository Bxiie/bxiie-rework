# Tenant visual settings implementation

Tenant visual settings are emitted as CSS custom properties from `App\Http\Controllers\Tenant\HomeController` for public pages and `App\Http\View\TenantAdminLayout` for tenant admin pages.

The following settings are intentionally shared across public and admin rendering:

- `topbar_background_color`, `topbar_background_opacity`, `topbar_media_uuid`
- `menu_background_enabled`, `menu_background_color`, `menu_background_opacity`, `menu_media_uuid`
- `heading_background_color`, `heading_background_opacity`
- `content_background_color`, `content_background_opacity`
- `text_background_color`, `text_background_opacity`
- `background_media_uuid`, `background_mode`, `background_tile_size`, `background_opacity`
- `header_drop_shadow_enabled`, `header_drop_shadow`

`SessionCookie` keeps singular and plural cookie helper aliases because different auth controllers historically called different names. Remove aliases only after all controllers are normalized.

# End of file.
