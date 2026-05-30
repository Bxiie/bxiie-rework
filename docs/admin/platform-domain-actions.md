# Platform Domain Actions

Platform admins manage custom domains at `/platform/admin/domains`.

## Verify DNS

Use **Verify DNS** after the tenant or customer points the hostname at the ArtsFolio server.

The button queues a `custom_domain.verify_dns` background job. The worker checks the hostname A record against `ARTSFOLIO_EXPECTED_IPV4`.

When verification succeeds, the domain status is set to `active`. The Caddy ask endpoint can then authorize on-demand TLS for the hostname.

## Render vhost

Do not use Render vhost for the current production deployment.

ArtsFolio now uses Caddy on-demand TLS instead of per-domain Apache vhost files. Old forms that still submit `render_vhost` are accepted for compatibility and redirected with a message, but no Apache artifact is required.

## Verification commands

```bash
php scripts/workers/run_once.php
curl -i 'http://127.0.0.1/caddy/ask?domain=example.com'
sudo systemctl status caddy --no-pager
sudo journalctl -u caddy -n 120 --no-pager
```

<!-- End of file. -->
