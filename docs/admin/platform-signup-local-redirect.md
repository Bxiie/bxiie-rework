# Platform Signup Redirect Behavior

## Production

After signup, users are redirected to:

```text
https://<slug>.artsfol.io/login
```

## Local development

Local development can use:

```text
APP_ENV=local
ARTSFOLIO_LOCAL_DEV_PORT=8080
```

This makes the redirect:

```text
http://<slug>.artsfol.io:8080/login
```

## Operational note

Production wildcard subdomains still require DNS and Caddy TLS support before arbitrary `<slug>.artsfol.io` domains work publicly.

<!-- End of file. -->
