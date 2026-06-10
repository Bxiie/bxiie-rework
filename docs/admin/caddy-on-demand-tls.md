# Caddy On-Demand TLS Operations

## What approves a certificate?

Caddy asks ArtsFolio before issuing a certificate for an unknown domain.

Approved domains:

```text
artsfol.io
www.artsfol.io
bxiie.com
www.bxiie.com
active tenant_domains.hostname rows
```

Rejected domains receive `403` from `/caddy/ask`.

## Operational checks

```bash
curl -i 'http://127.0.0.1/caddy/ask?domain=bxiie.artsfol.io'
curl -i 'http://127.0.0.1/caddy/ask?domain=not-a-tenant.artsfol.io'
sudo journalctl -u caddy -n 120 --no-pager
```

## Important

Do not leave a permissive temporary ask service running on port 8088. The app-backed endpoint is the durable authorization path.

<!-- End of file. -->
