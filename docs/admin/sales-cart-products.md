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

## Phase 2 admin controls

Phase 2 adds a **Sales & checkout** panel to the tenant-admin artwork upload and edit screens. The panel keeps the existing `artworks.price`, `artworks.is_one_off`, and `artworks.inventory_quantity` fields synchronized while also writing the new `artwork_sale_config` and `artwork_sale_variants` tables introduced in Phase 1.

### Product type

Use **One-off artwork** for original work where only one can be sold. Use **Multiple identical items** for prints, stickers, editions, or simple inventory-backed goods. Use **Sized / variant items** for clothing, shoes, or any product where the buyer must choose a size, fit, or similar option.

### Variant rows

Variant rows are the sellable choices for an artwork. A one-off sculpture or simple print can keep one active row named `Default`. A shirt might use rows such as `Unisex S`, `Unisex M`, and `Unisex XL`. Numeric sizing can use rows such as `Mens 10.5` or `Womens 8`.

For each variant row, administrators can set active state, label, size, gender or fit, optional price override, inventory quantity, optional shipping override, additional-item shipping override, and SKU/internal code.

### Shipping configuration

The artwork-level shipping controls define the default shipping behavior for the item. Variant rows may override shipping when the product uses variant-specific shipping, or when a particular size/item costs more to ship.

Phase 2 only stores the configuration. Public variant selection, variant-aware cart rows, Stripe shipping options, and payment-time inventory deduction arrive in later phases.

<!-- End of file. -->

