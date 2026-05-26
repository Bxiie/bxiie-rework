# Admin shell separation

ArtsFolio now treats platform administration and tenant administration as different operating contexts.

## Platform admin

Canonical route prefix:

```text
/platform/admin/*
```

Purpose:

- tenants
- custom domains
- platform pricing and billing settings
- jobs and workers
- email outbox
- platform stats
- platform audit log
- route inventory
- platform settings and platform CSS

Platform admin uses the dark/gold ArtsFolio control-plane shell and must not show tenant content-management links.

## Tenant admin

Canonical route prefix on tenant domains:

```text
/admin/*
```

Purpose:

- site settings
- content
- artworks
- portfolio sections
- events
- contact messages
- email signups
- billing view
- directory opt-in and thumbnail selection
- tenant stats
- tenant audit log

Tenant admin uses the tenant light shell and must not show platform operations links.

## Directory thumbnail selection

Tenant admins choose the public directory thumbnail at:

```text
/admin/directory
```

Only published artworks with a primary image are selectable. The selected value is stored in:

```text
tenant_settings.platform_directory_thumbnail_artwork_id
```

# End of file.
