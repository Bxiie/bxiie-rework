# Caddy On-Demand TLS

## Purpose

ArtsFolio uses Caddy on-demand TLS for tenant subdomains such as:

```text
testsite.artsfol.io
```

Wildcard certificates require DNS-01. Plain Caddy HTTP-01/TLS-ALPN cannot issue `*.artsfol.io` certificates without a DNS provider plugin.

## Ask endpoint

Caddy should call:

```text
http://127.0.0.1/caddy/ask?domain={host}
```

The app returns:

```text
200 ok
```

only for:

```text
platform domains
active tenant_domains.hostname rows
```

Unknown domains return `403`.

## Caddyfile pattern

```caddyfile
{
    email info@artsfol.io

    on_demand_tls {
        ask http://127.0.0.1/caddy/ask
    }
}

https:// {
    tls {
        on_demand
    }

    root * /var/www/artsfolio/public
    encode zstd gzip

    @notStatic {
        not file
    }

    rewrite @notStatic /index.php

    php_fastcgi unix//run/php/php8.4-fpm.sock {
        env APP_ENV production
        env ARTSFOLIO_ENV_FILE /etc/artsfolio/artsfolio.env
    }

    file_server
}
```

## Verification

```bash
curl -i 'http://127.0.0.1/caddy/ask?domain=bxiie.artsfol.io'
curl -i 'http://127.0.0.1/caddy/ask?domain=not-a-tenant.artsfol.io'
curl -I https://bxiie.artsfol.io/login
```

<!-- End of file. -->
