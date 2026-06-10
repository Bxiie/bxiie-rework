# Platform Settings Audit

## Scope

Platform settings updates can now write audit log rows.

## Audited action

```text
platform.settings.updated
```

## Browser route

```text
POST /admin/platform-settings
```

## Manual inspection

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/platform_settings_audit.php
```

## Notes

Repository-level scripts do not trigger controller audit logging. Audit events are created when the HTTP controller handles the settings update.

<!-- End of file. -->
