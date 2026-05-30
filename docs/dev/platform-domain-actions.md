# Platform Domain Action Contract

The canonical platform admin endpoint is:

```text
POST /platform/admin/domains/action
```

A compatibility route also accepts:

```text
POST /admin/domains/action
```

The compatibility route exists because older rendered admin pages posted to `/admin/domains/action`. New forms must post to `/platform/admin/domains/action`.

## Supported actions

### verify_dns

Queues `custom_domain.verify_dns`.

The worker checks the hostname A record against `ARTSFOLIO_EXPECTED_IPV4`. On success it sets `tenant_domains.status = 'active'`, which allows both tenant resolution and Caddy on-demand TLS authorization.

### render_vhost

Deprecated. Accepted only to avoid hard failures from stale forms. The action does not queue an Apache vhost job.

## Caddy behavior

Caddy uses `/caddy/ask` to approve domains. The ask endpoint approves active tenant domain rows. Apache vhost artifact generation is obsolete unless the deployment moves back to Apache-managed virtual hosts.

<!-- End of file. -->
