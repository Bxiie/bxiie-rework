# Subscription Billing Notifications

ArtsFolio queues tenant-owner billing emails through `email_outbox`.

Template files live under:

```text
template/email/billing/
```

Current billing template keys:

- `billing.checkout_completed`
- `billing.payment_failed`
- `billing.payment_recovered`
- `billing.subscription_canceled`
- `billing.plan_change_scheduled`
- `billing.plan_change_applied`
- `billing.plan_upgraded`

Notification sources:

- Stripe `checkout.session.completed` queues checkout completion.
- Stripe `invoice.payment_failed` queues failed-payment recovery instructions.
- Stripe `invoice.paid` queues recovery only when the subscription was previously past due.
- Stripe `customer.subscription.deleted` queues cancellation notice.
- Tenant Admin plan scheduling queues downgrade/cancellation scheduled notice.
- The scheduled billing applicator queues applied downgrade/cancellation notice.
- Immediate paid-to-paid upgrade queues upgrade notice.

Delivery still depends on the existing email worker processing `email_outbox`.

Useful verification query:

```sql
SELECT id, tenant_id, user_id, recipient_email, subject, template_key, status, available_at, sent_at, last_error
FROM email_outbox
WHERE template_key LIKE 'billing.%'
ORDER BY id DESC
LIMIT 25;
```

<!-- End of file. -->
