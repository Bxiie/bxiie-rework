# Admin Layout

## Scope

A shared admin stylesheet and layout helper now exist.

## Files

```text
public/assets/css/admin.css
app/Http/View/AdminLayout.php
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/admin_layout.php
```

## Usage

Controllers can gradually migrate from raw inline HTML to:

```php
AdminLayout::render($title, $body, $nav)
```

## Notes

This is intentionally light CSS. It is not the final design system.

<!-- End of file. -->
