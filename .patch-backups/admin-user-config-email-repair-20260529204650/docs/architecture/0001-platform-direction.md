# Arts Folio Platform Direction

artsfol.io is the parent platform.
bxiie.com is the first tenant/client.

## Routing

- artsfol.io: public marketing site
- app.artsfol.io: platform and tenant admin
- {tenant}.artsfol.io: default tenant public site
- custom domains: paid tier feature

## Core decisions

- Debian 13 server
- MariaDB direct install
- local tenant-isolated storage for now
- future object storage
- users are global
- users may belong to multiple tenants
- Google OAuth and Facebook Login supported
- billing and ecommerce deferred
- pricing tiers represented now as plans/entitlements
- tenant signup eventually self-service
- custom-domain vhost/cert automation handled by worker, not web request

## First tenant

- slug: bxiie
- default domain: bxiie.artsfol.io
- custom domain: bxiie.com
