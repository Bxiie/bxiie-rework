# Tenant sidebar readability

New tenant sites seed their editable `custom_css` from `public/assets/site.css` through `TenantSignupService::defaultTenantCss()`. Keep public navigation readability fixes in `public/assets/site.css` so newly created tenants inherit them.

The tenant-admin public header also uses the same menu visual variables, so `public/assets/tenant-admin.css` mirrors the readable menu text rule. The left tenant-admin application sidebar is intentionally dark and keeps white text.

Regression coverage lives in:

```text
scripts/test/tenant_sidebar_readability_static.php
```

Run it with:

```bash
php scripts/test/tenant_sidebar_readability_static.php
```

<!-- End of file. -->
