# Tenant getting-started implementation

`/admin/getting-started` is routed from `public/index.php` to:

```text
app/Http/Controllers/Tenant/Admin/GettingStartedController.php
```

The controller renders a first-run tenant-admin onboarding checklist. The page uses inline CSS and intentionally shows ArtsFolio platform branding because it is the handoff page after platform signup and OAuth site creation.

## Static coverage

The branding regression check is:

```text
scripts/test/tenant_getting_started_branding_static.php
```

It verifies that the page includes the platform logo asset, the platform branding wrapper class, accessible branding label, and ArtsFolio handoff copy.

## Future improvement

The onboarding copy is currently source-controlled. A future platform-admin content editor should store this copy in platform settings or a dedicated content table if non-developers need to edit it.

# End of file.
