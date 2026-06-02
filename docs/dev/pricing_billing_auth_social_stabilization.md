# Pricing, billing, auth, and social stabilization

This change keeps tenant-domain behavior consistent across platform subdomains and custom domains, adds missing tenant auth/signup routes, and aligns tenant billing usage with platform-admin pricing setup.

## Runtime behavior

- Tenant `/signup` now redirects to the tenant contact/signup page instead of returning a missing route.
- Tenant `/password/forgot` now accepts POST requests and queues branded password reset email through `email_outbox`.
- Preflight must not send SMTP mail. Email tests may queue local outbox rows, but worker delivery is guarded by `ARTSFOLIO_PREFLIGHT_SEND_EMAIL=1` and should only be used with a safe SMTP sink.
- Tenant footer renders configured Instagram, Facebook, and LinkedIn URLs.
- Abandoned cart reminders are queued by `scripts/email/queue_abandoned_cart_emails.php`; the script queues only and does not send SMTP directly.

## Billing and pricing

Platform-admin pricing setup now exposes the same features shown in tenant billing usage:

- artwork records
- media storage GB
- email subscribers
- contact messages
- custom domains
- admin users
- online checkout / allow sales

Tenant admins can upgrade or downgrade the selected plan from `/admin/billing`. The change updates `tenant_plan_assignments` and the legacy `tenant_settings.billing_plan` key for compatibility.

## Complementary tenants

Platform admins can mark a tenant complementary from the tenant detail screen. Complementary tenants are not billed for platform service, but platform commission still applies to sales.

## Abandoned cart reminders

Cart contact email is collected on the tenant cart page before checkout. The queue script sends reminders after 12 and 24 hours for active carts with an email address.

Recommended cron:

```cron
17 * * * * cd /var/www/artsfolio && /usr/bin/php scripts/email/queue_abandoned_cart_emails.php >> storage/logs/abandoned-cart-email.log 2>&1
```

# End of file.
