# Tenant getting-started page

The tenant getting-started page is shown after a new tenant site is created and is also available at `/admin/getting-started` on a tenant host.

The page is tenant-admin content, but it is part of the platform signup handoff. It must show ArtsFolio platform branding and should not look like an unowned tenant page.

## Editing the text

The current text is hardcoded in:

```text
app/Http/Controllers/Tenant/Admin/GettingStartedController.php
```

Edit the HTML returned by `GettingStartedController::index()` to change the onboarding copy, checklist labels, button text, or target links.

There is not currently a database-backed admin editor for this page. If editable onboarding copy becomes a product requirement, add a platform settings-backed content editor instead of asking tenant admins to edit source code.

## Verification

Run:

```bash
php scripts/test/tenant_getting_started_branding_static.php
./scripts/test/preflight.sh
```

# End of file.
