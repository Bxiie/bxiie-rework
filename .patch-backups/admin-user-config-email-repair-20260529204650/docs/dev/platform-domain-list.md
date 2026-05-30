# Platform Custom Domain List

## Scope

Platform admin now has a custom domain list screen.

## Route

```text
GET /admin/domains
```

## Repository

```text
App\Platform\Domains\DomainAdminRepository
```

## Access

Requires one of:

```text
platform owner
platform admin
platform support
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/platform_domain_list.php
```

<!-- End of file. -->
