# Plan commissions and Stripe processing

Platform Admin → Pricing defines a separate ArtsFolio sales commission for each
pricing plan. The launch defaults are:

- Free: 10%
- Studio: 5%
- Professional (`pro`): 3%
- Collective: 2%

Each plan also stores the percentage and fixed Stripe processing estimate used
for seller proceeds. ArtsFolio adds the plan commission and estimated Stripe
processing amount to the Stripe Connect application fee. The artist therefore
bears the processing cost economically, while ArtsFolio retains the plan
commission.

Commission applies to artwork subtotal. Stripe processing is estimated against
the buyer total, including shipping, because that is the amount charged.

<!-- End of file. -->
