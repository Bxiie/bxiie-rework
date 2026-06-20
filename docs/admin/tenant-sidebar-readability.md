# Tenant sidebar and menu readability

Tenant public navigation and the tenant-admin public header menu default to dark text on light/tan menu panels. This keeps newly created tenant sites legible before the artist customizes colors.

The setting currently follows the tenant text color with a hard-coded dark fallback:

- `--menu-text-color`, when supplied by CSS
- `--text-color`
- `#1f1a14`

The left tenant-admin application sidebar remains white text on a dark background. Do not change that sidebar to dark text unless its background is also changed.

To customize a tenant site manually, go to Tenant Admin -> Site and update the custom CSS or visual color settings.

<!-- End of file. -->
