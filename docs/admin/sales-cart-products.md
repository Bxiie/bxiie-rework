# Sales cart product setup

Shopping-cart phase 1 adds the data model for richer product setup. The admin UI for these fields arrives in phase 2, but the concepts are now fixed.

## Product types

One-off artwork uses one default variant with inventory quantity `1`. This is the right setting for originals and unique pieces.

Multiple identical items use one default variant with inventory quantity greater than `1`. This is the right setting for stickers, small prints, catalogs, and other items where buyers can choose a quantity but do not choose a size.

Sized or optioned items use one active variant per sellable option. Examples include `Unisex S`, `Unisex M`, `Womens L`, `Mens 10.5`, or any custom label needed by the artist.

## Shipping

The sale configuration supports no shipping charge, flat per item, flat per order, and variant-specific shipping. Phase 4 will calculate the order shipping total from these settings and send that value to Stripe Checkout without requiring Stripe products or saved Stripe shipping rates.

## Inventory

Every sellable item should have a variant row. Even a one-off original has a default variant. This keeps inventory, cart rows, order rows, and checkout reservations aligned once the runtime code becomes variant-aware.

## Cross-domain carts

Buyers may move between the tenant platform subdomain and the tenant custom domain. ArtsFolio keeps a separate secure cookie on each host and maps those cookies to the same cart through `sales_cart_aliases`.

<!-- End of file. -->
