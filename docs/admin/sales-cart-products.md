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



## Stripe success return and cart completion

After Stripe Checkout returns to `/checkout/success`, ArtsFolio verifies the Checkout Session directly with Stripe. If Stripe reports `payment_status=paid` and the session metadata matches the local order, ArtsFolio marks the order paid, consumes inventory reservations, marks the source cart `checked_out`, expires the current cart cookie, and shows the buyer an itemized order summary. Stripe webhooks remain supported and idempotent; the success return path is a safety net for delayed or misconfigured webhook delivery.


## Stripe pending checkout recovery

When a buyer clicks Checkout and returns before the webhook/success reconciliation finishes, ArtsFolio may already have a `checkout_pending` order for the active cart. The checkout action now checks for that pending order before creating a replacement order. If Stripe still reports the hosted Checkout Session as open, the buyer is redirected back to that same Stripe URL. If Stripe reports the Session as paid, ArtsFolio finalizes the order, consumes inventory reservations, marks the cart checked out, and expires the cart cookie. If the Session is expired, complete but unpaid, or missing because an earlier request failed midway, ArtsFolio releases the local reservations and starts a fresh checkout attempt.


## Stripe checkout resume recovery

When a cart has a `checkout_pending` order, ArtsFolio now asks Stripe for the live Checkout Session state before reusing the saved hosted checkout URL. Paid sessions are finalized locally, genuinely open sessions are resumed using Stripe's freshly returned URL, and expired, complete-but-unpaid, canceled, orphaned, or lookup-failed attempts are released so the buyer can start a fresh checkout. This prevents buyers from being sent back to Stripe's terminal “You're all done here” page while the local cart remains stuck in `checkout_pending`.

## Checkout success 500 guard

The `/checkout/success` route is buyer-safe around Stripe reconciliation. If the Stripe API lookup, local order lookup, order item lookup, cart-cookie expiration, or order-summary rendering fails, the route logs the exception with marker `[ArtsFolio checkout/success]` in `storage/logs/checkout_success.log` and still shows the best local order state available. Buyers are told not to pay again when Stripe may already have completed the payment.


## Paid Stripe reconciliation and inventory review

Paid Stripe sessions are authoritative for order payment status. If Stripe confirms a Checkout Session as paid but ArtsFolio finds that the original inventory reservation was already released, expired, completed, or missing, ArtsFolio now marks the order paid, checks out the source cart, and records an inventory-review note on the order instead of leaving the buyer on a pending checkout page. Administrators should review the order notes and adjust artwork or variant inventory manually when a paid reconciliation inventory note appears.


## Stripe reconciliation and legacy inventory sync

When Stripe reports a Checkout Session as paid, ArtsFolio marks the order paid, checks out the source cart, consumes any reserved variant inventory that can still be consumed, and then synchronizes the legacy artwork inventory fields from active sale variants. If inventory reservations drift because a buyer returns late or a session is reconciled after expiry, the order should still be recorded as paid and flagged for manual inventory review instead of remaining in checkout_pending.


## Stripe refunds and duplicate checkout protection

Tenant admins can open **Admin → Sales**, select an order, review the Stripe Checkout Session, PaymentIntent, items, totals, customer details, and recorded refunds, then create a Stripe refund directly from ArtsFolio. The refund action immediately calls Stripe; ArtsFolio records the Stripe refund id, amount, reason, status, actor, and raw Stripe response in `sales_order_refunds`.

For full refunds, the admin can choose to return completed order inventory to available variant stock. ArtsFolio records that restoration timestamp so the same order cannot be restocked twice from repeated refund records.

Checkout now guards against duplicate charges for the same cart. If a cart already has a paid order, `/cart/checkout` marks the cart checked out, expires the cart cookie, and redirects to the existing success page instead of creating or resuming another Stripe Checkout Session.


## Sales workflow fulfillment, refunds, and no-sale filters

Tenant admins use `/admin/sales` for the order desk. Paid Stripe orders are shown by default; enable **Show no-sale checkout rows** only when investigating unpaid, abandoned, canceled, or support-only checkout records.

Open an order to move it through **Ordered**, **Acknowledged**, **Packed**, **Shipped**, or **Refunded**. When saving a **Shipped** order, check **Email shipping details to buyer** to queue an email with the order number, carrier, tracking number, tracking URL, and item summary. ArtsFolio records `shipping_email_sent_at` and `shipping_email_outbox_id` on `sales_orders` so staff can see when the buyer notification was queued.

Use **Create Stripe refund** from the order review panel for ordinary paid-order refunds. The action immediately creates the Stripe refund, records the Stripe refund id in `sales_order_refunds`, and can return inventory to stock for full refunds.


## Sales order shipping display

Tenant-admin order review pages render buyer shipping addresses from the stored Stripe shipping JSON as readable address lines. If no shipping address was collected or saved, the order review shows an explicit `No shipping address recorded.` message instead of hiding the section.

<!-- End of sales shipping display update. -->

<!-- sales-shipping-contact-20260708 -->
## Buyer shipping contact collection

ArtsFolio checkout collects buyer email, buyer name, ship-to name, phone number, and shipping address on the cart before Stripe Checkout starts. Orders with a non-zero shipping charge require the phone number and complete shipping address before the Stripe session is created. Stripe Checkout also has phone-number collection enabled as a secondary confirmation source.

# End of sales shipping contact admin documentation.

### Shipping details and Stripe Checkout

The cart collects the buyer shipping address and phone number before redirecting to Stripe. ArtsFolio sends those details to Stripe as prefilled Customer and PaymentIntent shipping data, so the buyer should not need to enter the same shipping details twice. Admin order review remains the local source for fulfillment and shipping-notification emails.

### Sales order notes word wrapping

Sales order review notes preserve line breaks and wrap long words or pasted text within the admin panel. This prevents long notes from forcing horizontal scrolling or breaking the order review layout.
<!-- End of file. -->


## Admin logo rendering note

Tenant admin pages share header CSS with the broader ArtsFolio shell. Logo images
are guarded with intrinsic aspect-ratio rules so admin navigation, order pages,
and sales tools do not distort the ArtsFolio mark.
<!-- End of file. -->
