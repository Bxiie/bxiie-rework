# Sales readiness phase 1

Phase 1 adds catalog-level sales metadata without creating checkout orders.

Implemented behavior:
- Published, unsold `for_sale` artworks show the stored price on portfolio cards and artwork detail pages.
- Artwork detail pages link to the tenant contact form with the artwork title prefilled in the subject.
- Artwork records include `is_one_off` and `inventory_quantity` for original artwork versus inventory-backed items.
- Tenant settings include `sales_notes` so artists can explain shipping, pickup, payment, editions, and other sales details.
- Tenant public pages show a cookie consent prompt because sessions, analytics, spam checks, mailing-list forms, and the future cart use cookies.

Payment direction:
- Checkout will use Stripe in the next phase.
- Online checkout is restricted to paid plans.
- Platform commission applies to all sales once order capture exists.

# End of file.
