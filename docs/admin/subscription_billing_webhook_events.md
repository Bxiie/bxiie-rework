# Stripe Webhook Event Logging

ArtsFolio records Stripe webhook events in `stripe_webhook_events` before mutating local billing or sales state.

The `event_id` column is unique, so duplicate Stripe deliveries are ignored after an event has already been processed. Failed events can be retried by Stripe and will increment `attempt_count`.

Useful diagnostic queries:

```sql
SELECT event_id, event_type, status, attempt_count, response_code, received_at, processed_at, last_error
FROM stripe_webhook_events
ORDER BY id DESC
LIMIT 25;
```

```sql
SELECT event_type, status, COUNT(*) AS total
FROM stripe_webhook_events
GROUP BY event_type, status
ORDER BY event_type, status;
```

Investigate any rows that remain in `failed` or `processing` unexpectedly.

Expected webhook event set:

- `checkout.session.completed`
- `invoice.paid`
- `invoice.payment_failed`
- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`

<!-- End of file. -->
