# Rate Limiting

## Scope

A simple fixed-window database-backed rate limiter has been added.

## Table

```text
rate_limits
```

## Component

```text
App\Platform\Security\RateLimiter
```

## Current public route limits

```text
POST /contact   5 submissions per 5 minutes per tenant/IP
POST /signup    5 submissions per 5 minutes per tenant/IP
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/database/migrate.php
php scripts/test/rate_limiter.php
```

## Production notes

This is a reasonable first-pass limiter, not final abuse protection.

Before launch add:

```text
reverse proxy trusted IP handling
Cloudflare/proxy-aware client IP detection
bot protection
per-email throttles
audit logs for repeated abuse
cleanup job for old rate limit rows
```

<!-- End of file. -->
