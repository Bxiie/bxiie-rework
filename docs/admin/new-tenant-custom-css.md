# New-tenant Custom CSS

When a tenant is created, ArtsFolio copies the current
`public/assets/site.css` into the tenant's `custom_css` setting.

The generated editor content includes:

- stylesheet load-order guidance;
- a safe editing workflow;
- commonly used CSS variables;
- a selector map;
- responsive-design guidance;
- a clearly marked area for tenant additions.

Only the public tenant stylesheet is copied. Platform and administration CSS
are intentionally excluded.

Provisioning never overwrites an existing `custom_css` value. A retried
provisioning job therefore cannot erase tenant edits.

<!-- End of file. -->
