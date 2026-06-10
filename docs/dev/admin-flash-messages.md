# Admin Flash Messages

## Scope

Admin screens now have a tiny session-backed flash message helper.

## Files

```text
app/Support/Flash/FlashMessages.php
app/Http/View/AdminLayout.php
public/assets/css/admin.css
```

## Current usage

```text
contact message status updates
email signup consent updates
tenant settings saves
platform settings saves
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/flash_messages.php
```

<!-- End of file. -->
