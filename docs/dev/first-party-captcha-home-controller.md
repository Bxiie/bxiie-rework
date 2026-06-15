# First-party CAPTCHA controller integration

Tenant public pages must not call the removed `HomeController::TurnstileWidget()` helper. Tenant contact and signup forms use `App\Services\FirstPartyCaptcha::render()` for markup and the tenant contact/signup POST controllers use `FirstPartyCaptcha::verify()` for submission validation.

This keeps tenant sites independent from Google Cloudflare Turnstile domain restrictions and prevents custom-domain tenants from rendering invalid-domain third-party widgets.

# End of file.
