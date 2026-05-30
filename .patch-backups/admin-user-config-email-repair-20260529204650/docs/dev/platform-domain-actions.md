# Platform Custom Domain Actions

## Scope

Platform admins can queue maintenance jobs from the custom domain list.

## Route

```text
POST /admin/domains/action
```

## Actions

```text
verify_dns
render_vhost
```

## Jobs queued

```text
custom_domain.verify_dns
custom_domain.render_vhost
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/platform_domain_actions.php
```

<!-- End of file. -->
