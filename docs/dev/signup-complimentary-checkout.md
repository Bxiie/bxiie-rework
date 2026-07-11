# Complimentary signup access and Stripe checkout

A signup code with `free_access_months > 0` grants the selected plan without an immediate ArtsFolio subscription charge.

During tenant creation:

1. `TenantSignupService` validates the signup code and selected plan.
2. The service records the tenant plan assignment with `billing_status = trial`, `status = trial`, and `complimentary_until`.
3. The signup result includes `complimentary_months`, `complimentary_until`, and `requires_immediate_checkout`.
4. `SignupController` redirects to Stripe only when `requires_immediate_checkout` is true.
5. A paid plan selected with complimentary months redirects to the tenant getting-started page instead of Stripe Checkout.

This behavior prevents paid plan price alone from overriding a valid complimentary period.

The complimentary grant does not refund charges already captured by Stripe. Existing erroneous charges must be reviewed and refunded through ArtsFolio billing administration or Stripe.

<!-- End of file. -->
