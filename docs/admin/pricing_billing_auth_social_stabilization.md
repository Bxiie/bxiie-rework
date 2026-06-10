# Pricing, billing, tenant auth, and footer links

## Platform pricing

Open `/platform/admin/pricing` to configure:

- monthly price
- artwork record limit
- media storage limit
- email subscriber limit
- contact message limit
- admin user limit
- custom domain inclusion
- whether online checkout is allowed
- platform sales commission

Paid plans may enable online checkout. Free plans should leave online checkout disabled.

## Tenant billing override

Open a tenant from `/platform/admin/tenants`, then use **Complementary tenant** when the tenant should not be billed for platform service. Complementary tenants still owe platform commission on sales.

## Tenant billing

Tenant owners can open `/admin/billing` and change plans. The feature usage table mirrors the platform pricing setup fields.

## Social footer links

Tenant content settings support Instagram, Facebook, and LinkedIn URLs. Valid `http` or `https` URLs are shown in the footer of tenant public pages.

## Abandoned cart email

Abandoned cart reminders are queued, not sent directly, by `scripts/email/queue_abandoned_cart_emails.php`. The normal worker performs delivery.

# End of file.
