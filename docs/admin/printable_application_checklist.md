# Printable ArtsFolio application checklist

Use this checklist for a manual release pass when automated UI coverage is not enough. Print one copy per environment and mark each item with Pass, Fail, N/A, and notes.

## Public platform

| Check | Pass | Fail | N/A | Notes |
| --- | --- | --- | --- | --- |
| Platform home loads at `https://artsfol.io/` with ArtsFolio branding. | ☐ | ☐ | ☐ | |
| Pricing page lists active plans and sales economics. | ☐ | ☐ | ☐ | |
| Directory page loads and does not expose suspended or deleted tenants. | ☐ | ☐ | ☐ | |
| Platform contact form is branded, styled, protected by captcha when configured, and records IP/location where available. | ☐ | ☐ | ☐ | |
| Signup flow honors one-time and blanket signup codes. | ☐ | ☐ | ☐ | |
| Login failure shows a branded retry page, not raw `Invalid Login`. | ☐ | ☐ | ☐ | |

## Tenant public site

| Check | Pass | Fail | N/A | Notes |
| --- | --- | --- | --- | --- |
| Tenant subdomain loads tenant content, not platform marketing content. | ☐ | ☐ | ☐ | |
| Custom domain loads the same tenant content as the tenant subdomain. | ☐ | ☐ | ☐ | |
| Header, menu, page backgrounds, text surfaces, and drop shadows reflect tenant settings. | ☐ | ☐ | ☐ | |
| Phone and tablet layouts work without horizontal scrolling or clipped nav. | ☐ | ☐ | ☐ | |
| Portfolio tabs appear in configured order. | ☐ | ☐ | ☐ | |
| Artwork detail pages show correct media, metadata, sale status, and cart controls. | ☐ | ☐ | ☐ | |
| Public Admin tab is hidden from anonymous visitors and visible only after login. | ☐ | ☐ | ☐ | |
| Contact form shows success on success and useful error on failure. | ☐ | ☐ | ☐ | |
| Footer email signup works and records IP/location fields when available. | ☐ | ☐ | ☐ | |

## Tenant admin

| Check | Pass | Fail | N/A | Notes |
| --- | --- | --- | --- | --- |
| `/admin` dashboard shows correct published artwork, for-sale artwork, 30-day views, subscribers, messages, and orders. | ☐ | ☐ | ☐ | |
| Artwork upload creates portfolio and site images correctly. | ☐ | ☐ | ☐ | |
| Artwork edit saves status, section selection, sale status, price, one-off/multiple inventory, and thumbnail behavior. | ☐ | ☐ | ☐ | |
| Publish/unpublish controls show only the valid next action. | ☐ | ☐ | ☐ | |
| Settings save site title, artist name, colors, images, opacity, topbar, backgrounds, and CSS. | ☐ | ☐ | ☐ | |
| Site Images picklists show only published Site Images with thumbnails. | ☐ | ☐ | ☐ | |
| Contact messages list, status changes, delete/archive, CSV export, IP, and location fields work. | ☐ | ☐ | ☐ | |
| Email signups list, consent changes, delete/archive, CSV export, IP, and location fields work. | ☐ | ☐ | ☐ | |
| Billing page shows plan, complementary status, usage limits, and seller-proceeds examples. | ☐ | ☐ | ☐ | |
| Users page can invite, resend, delete, and promote tenant users according to role rules. | ☐ | ☐ | ☐ | |
| Sales page lists orders and workflow updates. | ☐ | ☐ | ☐ | |
| `/admin/sales/analytics` loads and shows tenant sales analytics. | ☐ | ☐ | ☐ | |
| Stats page shows aggregate day-of-week and hour-of-day charts for the selected range. | ☐ | ☐ | ☐ | |
| Tenant admin never links to platform admin pages. | ☐ | ☐ | ☐ | |

## Platform admin

| Check | Pass | Fail | N/A | Notes |
| --- | --- | --- | --- | --- |
| `/platform/admin` dashboard shows real tenant counts, plan signals, sales metrics, contact queue, jobs, and worker warning. | ☐ | ☐ | ☐ | |
| Tenant detail billing override saves and returns `Tenant billing override updated.` | ☐ | ☐ | ☐ | |
| Tenant user password update returns `Tenant user password updated.` | ☐ | ☐ | ☐ | |
| Tenant status suspend/archive/delete controls preserve custom domains unless explicitly changed. | ☐ | ☐ | ☐ | |
| Pricing editor saves plan economics and platform commission settings. | ☐ | ☐ | ☐ | |
| `/platform/admin/sales/analytics` loads and shows platform sales analytics. | ☐ | ☐ | ☐ | |
| Domains page shows DNS verification status and last check result. | ☐ | ☐ | ☐ | |
| Jobs and workers pages show current queue and heartbeat status. | ☐ | ☐ | ☐ | |
| Email outbox can inspect queued/sent/failed lifecycle emails. | ☐ | ☐ | ☐ | |
| Audit log records sensitive admin actions. | ☐ | ☐ | ☐ | |

## Automated release gate

| Check | Pass | Fail | N/A | Notes |
| --- | --- | --- | --- | --- |
| `composer dump-autoload` succeeds. | ☐ | ☐ | ☐ | |
| `find app public scripts bin -name '*.php' -print0 | xargs -0 -n1 php -l` succeeds. | ☐ | ☐ | ☐ | |
| `./scripts/test/preflight.sh` succeeds without deleting production tenant/domain data. | ☐ | ☐ | ☐ | |
| `php scripts/test/sales_phase3_static.php` succeeds. | ☐ | ☐ | ☐ | |
| `php scripts/test/dashboard_real_schema_static.php` succeeds. | ☐ | ☐ | ☐ | |
| Browser smoke tests pass for platform and bxiie tenant routes. | ☐ | ☐ | ☐ | |

# End of file.
