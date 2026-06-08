# Email list management and custom-domain session bridge

Migration `0032_email_signup_management_and_session_bridge.sql` adds `email_signups.notes`, search indexes, and `tenant_session_bridge_tickets`.

The tenant email admin UI is implemented in `app/Http/Controllers/Tenant/Admin/EmailSignupsController.php` and persistence lives in `app/Tenant/Signup/EmailSignupRepository.php`.

Cross-domain login cannot be solved by one cookie because custom domains and tenant subdomains such as `{tenant}.artsfol.io` are unrelated cookie scopes. The implemented flow uses a one-time short-lived ticket:

1. A logged-in admin on any tenant-owned host requests `/auth/tenant-session/bridge`.
2. The bridge validates the requested return host belongs to the same tenant.
3. The bridge stores a hashed one-time ticket in `tenant_session_bridge_tickets`.
4. The browser redirects back to the custom domain with `af_session_bridge`.
5. The destination tenant-owned host consumes the ticket, creates a host-local browser session, sets `artsfolio_session`, and redirects to the clean URL.

Run static coverage with:

```bash
php scripts/test/email_signup_management_static.php
```

<!-- End of file. -->
