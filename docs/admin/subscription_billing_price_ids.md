# Stable Stripe Price IDs

Each paid ArtsFolio plan must have a durable Stripe monthly Price ID configured in Platform Admin → Pricing before paid signup or paid plan changes can bill through Stripe.

Free plans should leave Stripe Product ID and Stripe monthly Price ID blank.

Required Stripe webhook events after this pass:

- `checkout.session.completed`
- `invoice.paid`
- `invoice.payment_failed`
- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`

Paid signup and free-to-paid changes use Stripe Checkout with `line_items[0][price]` set to the configured plan Price ID. Paid-to-paid upgrades can update the existing Stripe subscription item and request immediate invoicing. Scheduled downgrades use the target plan Price ID when the recurrence boundary arrives.

<!-- End of file. -->
