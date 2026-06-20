# Tenant color palettes

Tenant administrators can use color/background palette buttons at the top of **Tenant Admin → Settings → Colors and background**.

Each palette fills the existing color, opacity, background, menu, heading, text-spread, and artwork-card controls. The palette does not save immediately. After applying a palette, the administrator can adjust any individual picker or text field and then click **Save site settings**.

The first palette is **Default**, which matches the new-site default ArtsFolio tenant palette.

Palette choices:

- Default
- Gallery White
- Ink Studio
- Desert Clay
- Forest Linen
- Signal Blue
- Rose Paper
- Concrete Pop
- Midnight Olive
- Ultraviolet Paper

Palette buttons do not change selected background images. Background, top bar, menu, and artwork-card images remain controlled by the existing Site Images pickers.

Opacity fields in this section accept hundredth values such as `0.72`; the browser should not force rounding to `.70` or `.75`.

Palette clicks update the visible settings-page preview immediately, including the top navigation bar, menu panel, and page surface variables. Save site settings is still required to persist the selected values.

Each palette also sets separate **Top bar text color** and **Menu text color** values. These keep the site title and navigation legible when a palette uses dark surfaces, such as Ink Studio. The palette buttons include a miniature preview showing the top bar, menu pill, and page/surface colors so the palette mood is visible before applying it.


Palette buttons are styled from the same colors they apply. If a browser holds an old CSS file, hard refresh once after deployment; the tenant admin stylesheet is versioned for palette-button updates.

# End of file.
