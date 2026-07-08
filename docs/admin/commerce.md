# Commerce operations

ArtsFolio artwork sales use Stripe Checkout with Stripe Connect destination charges. Artists connect an Express Stripe account from their site admin by clicking Settings, then Connect Stripe in the payout panel.

## Operator checklist

1. Configure the platform Stripe secret key in Platform Admin settings.
2. Confirm Stripe Connect is enabled for the Stripe platform account.
3. Ask the artist to click Settings in the sidebar and complete Connect Stripe onboarding.
4. Confirm the artist payout panel reports connected and ready for checkout.
5. Place a small test order in Stripe test mode before enabling live sales.

Checkout is intentionally paused when the connected account is missing or not ready. Do not bypass this in production, because otherwise a buyer can create a platform-only charge that does not route the artwork proceeds to the artist.

## Live commerce launch checklist

Run a full Stripe test-mode sale before enabling live commerce for any artist.

1. Confirm Platform Admin has the Stripe secret key and webhook secret configured.
2. Confirm Stripe Connect is enabled on the platform Stripe account.
3. Confirm the artist completed Connect Stripe onboarding from Settings in the sidebar.
4. Confirm checkout is blocked while the connected account is not ready.
5. Create a low-price test artwork or variant and publish it.
6. Add the artwork to cart and complete Stripe Checkout in test mode.
7. Confirm the order is marked paid in ArtsFolio and in Stripe.
8. Confirm the payment intent uses the artist connected account as the destination.
9. Confirm inventory and order workflow statuses changed as expected.
10. Confirm buyer and artist emails are queued or sent.
11. Issue a partial refund and compare ArtsFolio against Stripe.
12. Issue a full refund on a separate order and compare ArtsFolio against Stripe.

No further refund should be attempted after a Stripe error until the local order row and the Stripe dashboard are compared.

<!-- End of file. -->
