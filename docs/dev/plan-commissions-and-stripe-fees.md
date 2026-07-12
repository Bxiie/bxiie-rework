# Plan commissions and Stripe processing

`plans.platform_commission_basis_points` is the authoritative commission rate.
The historical `platform_settings.platform_sales_commission_basis_points` value
is retained only as a compatibility fallback.

Checkout computes:

- commission = artwork subtotal × plan commission rate
- estimated Stripe fee = buyer total × plan card rate + fixed card fee
- seller net = buyer total − commission − estimated Stripe fee
- Connect application fee = commission + estimated Stripe fee

ArtsFolio currently uses Stripe destination charges. Stripe debits processing
fees from the platform balance for that charge type, so including the estimated
processing amount in the application fee causes the artist to bear that cost
economically. Order and PaymentIntent metadata preserve the split for audit.

<!-- End of file. -->
