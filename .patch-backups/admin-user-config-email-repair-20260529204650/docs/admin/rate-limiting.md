# Rate Limiting Administration

## Current implementation

Database-backed fixed-window rate limiting exists for public contact and signup POST routes.

## Current limits

```text
5 submissions per 5 minutes per tenant/IP
```

## Production requirements

```text
proxy-aware IP detection
cleanup job
admin abuse visibility
tenant-level spam controls
CAPTCHA or equivalent challenge
```

<!-- End of file. -->
