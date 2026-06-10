# Admin Pagination

## Scope

Admin list screens now have lightweight pagination helpers.

## Helper

```text
App\Support\Pagination\Pagination
```

## Updated screens

```text
platform audit log
tenant audit log
tenant contact messages
tenant email signups
```

## Query parameters

```text
page
limit
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/pagination.php
```

<!-- End of file. -->
