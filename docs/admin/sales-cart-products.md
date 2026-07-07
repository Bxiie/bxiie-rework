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


## Phase 4 checkout behavior

Phase 4 activates the stored sales settings during checkout. When a buyer starts Stripe Checkout, ArtsFolio reserves the exact variant in the cart, snapshots the chosen size or fit onto the order, computes item-level shipping, and sends the total shipping amount to Stripe as inline shipping. No Stripe catalog setup is required for individual artworks, prints, stickers, or sized items.

Payment completion decrements the purchased variant inventory. If all active variants are sold out, the artwork is marked sold. Administrators should continue editing inventory from the artwork sale configuration panel rather than editing Stripe products.


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


## Phase 3 public buyer behavior

The artwork sales settings configured by administrators now appear on public artwork pages. The variant-aware public cart forms let buyers choose a size, fit, or other option before adding an item to the cart. If the cart is not empty, tenant pages show a cart link in the site navigation.

When a tenant uses both an ArtsFolio subdomain and a custom domain, the buyer cart follows between active domains through the cart bridge.

## Abandoned-cart reminders

ArtsFolio sends abandoned-cart reminders only when the system knows the buyer email. The reminders are queued after 1, 3, and 7 days, provided the cart is still active and at least one item remains available.

The buyer receives a restore link that opens the cart on the tenant’s preferred public domain. If a size, print, sticker, or original artwork is no longer available, the cart page shows the current availability before checkout.

The reminder system respects tenant email-list suppression records for unsubscribed, bounced, and complained addresses. It queues mail through the platform email outbox, so delivery and retry behavior remain visible in Platform Admin email diagnostics.


## Shipping profiles

Sales-enabled tenants can use shipping profiles to group similar products into one buyer-friendly shipping calculation. For example, a **Small flat items** profile can charge $5.00 once for stickers, postcards, or small flat prints even when the buyer adds several different artworks to the same cart.

Default profiles seeded by the platform:

- **Small flat items**: one flat $5.00 profile-level charge.
- **Small merchandise**: $6.00 first item, $2.00 additional items, capped at $14.00.
- **Free shipping**: $0.00 shipping.
- **Large artwork / quoted shipping**: checkout disabled until the artist quotes shipping.

On the artwork definition page, select the profile that best fits the item. Use **Small flat items** for sticker-style products where many different items can travel in the same envelope.

## 2026-07-07 cart display fix

The cart review page uses the same shipping-profile grouping as checkout and order creation. Multiple products assigned to the same **Small flat items** profile show one shared shipping total instead of a separate flat charge per row.



## Stripe checkout shipping consistency

The buyer cart, sales economics calculation, order creation, and Stripe Checkout session all use grouped shipping-profile allocation. Flat profile products that share a profile, such as stickers, should produce one profile-level shipping amount in the cart and the same shipping amount in Stripe. The order row's `shipping_cents` is the final source of truth passed to Stripe Checkout.

<!-- End of file. -->
