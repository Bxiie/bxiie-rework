# Tenant custom CAPTCHA

Tenant public contact and email-list forms use ArtsFolio's built-in first-party CAPTCHA instead of Cloudflare Turnstile.

## Why

Cloudflare Turnstile remains enabled for ArtsFolio platform-domain forms. Tenant subdomains and custom domains do not inherit platform Turnstile keys because widget hostname limits do not scale with multi-tenant custom domains.

## Runtime behavior

`App\Services\FirstPartyCaptcha` supports two modes:

- Turnstile mode when a site key/secret key is supplied.
- First-party mode when no site key/secret key is supplied.

Tenant controllers intentionally pass blank Turnstile keys so tenant forms render the first-party challenge. Platform marketing/contact forms continue to pass platform Turnstile settings.

The first-party challenge uses:

- session-backed single-use token
- short expiry
- minimum dwell time
- hidden honeypot field
- required human confirmation checkbox

## Files

- `app/Services/FirstPartyCaptcha.php`
- `app/Http/Controllers/Tenant/HomeController.php`
- `app/Http/Controllers/Tenant/ContactController.php`
- `app/Http/Controllers/Tenant/SignupController.php`
- `app/Http/Controllers/Tenant/Admin/SettingsController.php`
- `scripts/test/tenant_custom_captcha_static.php`

## Verification

```bash
php -l app/Services/FirstPartyCaptcha.php
php scripts/test/tenant_custom_captcha_static.php
./scripts/test/preflight.sh
```

<!-- End of file. -->
