# Sales inventory safety

ArtsFolio reserves inventory when a buyer starts Stripe Checkout.

- The hold lasts 35 minutes.
- Stripe Checkout is configured to expire after 30 minutes.
- Other buyers cannot reserve the held quantity.
- Failed checkout creation releases the hold immediately.
- Abandoned holds are released by the background worker.
- Payment completion converts the hold into a completed sale and decrements artwork inventory exactly once.

If checkout inventory appears stuck, run:

```bash
php scripts/maintenance/release_expired_sales_reservations.php
```

Then inspect `sales_inventory_reservations`, `sales_orders`, and the affected artwork before changing data.

# End of file.
