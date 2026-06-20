# Tenant settings subpages

Tenant Admin -> Settings is split into subpages so large tenant-site configuration areas are easier to operate.

Subpages:

- Identity: site title, artist name, browser title, copyright name, admin notification email, home intro text, public navigation labels, and public page slugs.
- Typography: typography presets, font family pickers, and text-size controls for public pages.
- Colors & Backgrounds: color palettes, text and background colors, top bar/menu colors, background opacity controls, and site image pickers.
- Miscellaneous: public sales notes, Stripe connected account ID, default artwork order, spam-protection note, and exhibition display options.
- Custom CSS: advanced tenant CSS loaded after the standard public stylesheet.

Each subpage has its own `Save site settings` button. Saving one subpage writes only the fields on that subpage and then returns to that same subpage with a saved notice. Hidden settings on other subpages are not cleared.

The public URL pattern is `/admin/settings?section=<section-key>`. Unknown section keys fall back to Identity.

# End of file.
