# Billing and Plan Changes

Free plans do not require a card. Paid plans require card details and are billed immediately, then monthly. Downgrades and cancellations keep current-plan features until the recurrence date.

<!-- End of file. -->

## Failed payments and scheduled changes

If a subscription payment fails, ArtsFolio marks the billing record as requiring action. Tenant owners should update payment details through the Stripe-hosted billing flow when prompted.

Downgrades and cancellations do not remove current-plan access immediately. They take effect on the billing recurrence date shown in Tenant Admin billing.

<!-- End of file. -->

## Paid-plan billing reliability

Paid plans are linked to Stripe subscription prices by the platform administrator. If a paid plan is not fully configured, ArtsFolio will stop before collecting payment and ask the administrator to finish billing setup.

<!-- End of file. -->

## Updating payment details

Tenant owners can update payment details from Tenant Admin → Billing. The update opens Stripe Billing Portal, where card details and invoices are managed securely by Stripe.

After returning from Stripe, ArtsFolio continues showing the current billing status. Failed payments may take a moment to clear because Stripe sends confirmation through webhook events.

<!-- End of file. -->
