# Billing economics and payout transparency

ArtsFolio plan economics are configured from Platform Admin → Pricing. Plans define the subscription price, feature limits, sales availability, estimated credit card percentage fee, and estimated fixed credit card fee. Platform sales commission remains a platform-level setting.

Order creation stores gross sale amount, platform commission, estimated credit card fee, and estimated seller net revenue. Stripe Checkout application fees include platform commission plus the estimated card fee so complimentary tenants and paid tenants both pay sales economics while subscription billing can still be waived.

The tenant signup route inventory test now requires both GET /signup and POST /signup because direct signup links are part of onboarding, pricing, invite, and abandoned-cart flows.

# End of file.
