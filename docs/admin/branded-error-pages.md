# Branded error pages

Every browser-facing error page should display ArtsFolio or tenant branding. Visitors should not see raw routing messages, PHP warnings, stack traces, or default Apache error pages.

## What users see

- Platform pages show ArtsFolio branding.
- Tenant pages show the tenant site name and a `Powered by ArtsFolio` footer.
- Missing pages, forbidden pages, suspended/unavailable site pages, and most application failures use the same branded error shell.

## What admins should check

Use the browser or `curl` to test missing pages after deployment:

```bash
curl -ksS https://artsfol.io/__definitely_missing_platform_page__ | head
curl -ksS https://bxiie.artsfol.io/__definitely_missing_tenant_page__ | head
```

The response should contain normal HTML with `/assets/error.css`, not raw plaintext.

## Escalation clues

Escalate to development if any public browser page shows:

- `No route for GET`
- `Application error`
- `Fatal error`
- `Stack trace`
- default Apache error markup
- unstyled plaintext errors

Application exception details are logged server-side and intentionally hidden from users.


## CSRF failures

Invalid or expired CSRF token responses must use `Response::invalidCsrf()` so security failures render inside the same platform or tenant branded error shell as 404 and 500 responses. Controllers must not return raw `<h1>Invalid CSRF token</h1>` markup.


## Platform user lifecycle errors

Platform-admin user suspend/delete validation failures render through the branded error system instead of raw `<h1>` responses. The default platform user list hides soft-deleted users and displays suspended users with their real `users.status` value.

<!-- End of file. -->


