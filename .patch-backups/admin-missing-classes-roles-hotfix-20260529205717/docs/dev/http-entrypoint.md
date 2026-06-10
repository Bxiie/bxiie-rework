# HTTP Entrypoint

## Scope

The first HTTP entrypoint has been added for local platform-core verification.

## Files

```text
public/index.php
app/Http/Request.php
app/Http/Response.php
app/Http/Router.php
app/Http/Middleware/ResolveTenant.php
app/Http/Controllers/Platform/HomeController.php
app/Http/Controllers/Tenant/HomeController.php
```

## Local run command

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php -S 127.0.0.1:8080 -t public
```

## Host header verification

Use curl with explicit Host headers:

```bash
curl -H "Host: artsfol.io" http://127.0.0.1:8080/
curl -H "Host: artsfol.io" http://127.0.0.1:8080/pricing
curl -H "Host: bxiie.com" http://127.0.0.1:8080/
curl -H "Host: bxiie.artsfol.io" http://127.0.0.1:8080/portfolio
```

## Current routing behavior

Platform routes are used when no tenant resolves.

Tenant routes are used when the Host header resolves through tenant_domains.

## Current limitations

```text
No dynamic artwork route yet.
No template engine yet.
No admin route split yet.
No authentication route implementation yet.
No CSS/theme rendering yet.
```

<!-- End of file. -->
