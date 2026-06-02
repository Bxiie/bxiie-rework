# Platform pricing implementation

Pricing data is stored in the `plans` table. Migration `0023_pricing_limits_commission.sql` adds editable display and limit columns:

- `description`
- `allowed_artworks`
- `allowed_email_addresses`
- `display_order`

Global platform sales commission is stored in `platform_settings` as `platform_sales_commission_basis_points`. The value is basis points, so `500` means `5.00%`.

Primary code paths:

- `app/Http/Controllers/Platform/Admin/PricingController.php` renders and saves platform-admin pricing.
- `app/Http/Controllers/Platform/PricingController.php` renders public pricing.
- `app/Http/Controllers/Tenant/Admin/BillingController.php` displays current tenant plan context and commission disclosure.
- `app/Http/Controllers/Tenant/HomeController.php` shows the ArtsFolio link/notification on free tenant pages.

Regression coverage:

```bash
php scripts/test/platform_pricing_static.php
```

# End of file.
