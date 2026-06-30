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

Abandoned cart reminders are queued at 1, 3, and 7 days, not sent directly, by `scripts/email/queue_abandoned_cart_emails.php` or the `sales.cart.queue_abandoned_reminders` worker job. The normal email worker performs delivery.

# End of file.
