# Default Tenant Admin color contrast

Tenant Admin sidebar text uses the tenant's configured `menu_text_color`.
A final high-specificity stylesheet layer prevents older forced-white rules
from overriding the configured contrast color.

This applies to:

- site and account information;
- sidebar navigation;
- active and hover states;
- footer links;
- the Upload Artwork action.

The stylesheet URL is versioned so browsers fetch the corrected rules after
deployment.

<!-- End of file. -->
