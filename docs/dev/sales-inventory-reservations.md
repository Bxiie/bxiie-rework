# Sales inventory reservations

Phase 7 prevents concurrent Stripe checkouts from overselling originals or limited inventory.

## Lifecycle

1. Checkout locks each artwork row.
2. Available quantity is calculated as physical inventory minus all live reservations.
3. The order and one reservation per artwork are created in the same transaction.
4. Reservations last 35 minutes. Stripe Checkout expires after 30 minutes.
5. A successful Stripe webhook atomically decrements inventory and marks reservations completed.
6. Failed session creation releases the order reservations immediately.
7. A recurring background job expires abandoned reservations every five minutes.

Webhook completion is idempotent. Repeated `checkout.session.completed` events do not decrement inventory twice.

## Manual recovery

```bash
php scripts/maintenance/release_expired_sales_reservations.php
```

Do not edit reservation rows manually unless repairing a known incident. Reservation state, order payment state, and artwork inventory must remain coherent.

# End of file.
