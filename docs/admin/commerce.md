# Commerce operations

ArtsFolio artwork sales use Stripe Checkout with Stripe Connect destination charges. Artists connect an Express Stripe account from their site admin by clicking Settings, then Connect Stripe in the payout panel.

## Operator checklist

1. Configure the platform Stripe secret key in Platform Admin settings.
2. Confirm Stripe Connect is enabled for the Stripe platform account.
3. Ask the artist to click Settings in the sidebar and complete Connect Stripe onboarding.
4. Confirm the artist payout panel reports connected and ready for checkout.
5. Place a small test order in Stripe test mode before enabling live sales.

Checkout is intentionally paused when the connected account is missing or not ready. Do not bypass this in production, because otherwise a buyer can create a platform-only charge that does not route the artwork proceeds to the artist.

<!-- End of file. -->
