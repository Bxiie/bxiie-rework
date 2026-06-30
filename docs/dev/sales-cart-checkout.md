# Sales cart checkout architecture

Shopping-cart phase 1 creates the durable data model for tenant-scoped carts that can support originals, multiple-quantity goods, sized products, item-level shipping, abandoned-cart reminders, and domain-portable cart identity.

## Current phase

Phase 1 is schema-first and backward-compatible. Existing artwork-level cart behavior continues to work. Runtime checkout still reads the older artwork fields until later phases update the admin UI, public add-to-cart form, cart rendering, Stripe payload, and webhook inventory completion.

## Data model

`artwork_sale_config` stores one sale configuration row per artwork. It owns sale type, option schema, gender schema, base price, currency, shipping mode, shipping defaults, allowed shipping countries, checkout enablement, and whether a shipping address is required.

`artwork_sale_variants` stores one sellable row per option. A one-off artwork gets a single `Default` variant. Multiple identical goods also get one variant with quantity greater than one. Sized goods get one active row per size and fit, such as `Unisex XL` or `Mens 10.5`.

`sales_cart_items`, `sales_order_items`, and `sales_inventory_reservations` now have nullable `variant_id` and variant snapshot columns. They remain nullable during phase 1 so old code keeps working while rows are backfilled to the default variant where possible.

`sales_orders.shipping_cents` stores the order-level shipping total that will later be sent to Stripe Checkout as an inline shipping option.

## Domain-portable carts

Each tenant hostname keeps its own first-party cart cookie. Browser cookies cannot be shared between `tenant.artsfol.io` and a custom domain such as `bxiie.com`, so ArtsFolio will bridge them server-side.

`sales_cart_aliases` maps one or more hashed local cart tokens to the same canonical `sales_carts.id`. Raw cart tokens must not be stored in this table. Runtime code should store `hash_hmac('sha256', $rawToken, $appSecret)` in `cart_token_hash`.

Future bridge endpoints should validate tenant, current host, target host, cart ownership, token signature, and token expiry before attaching an alias. Cart contents and buyer email must not be embedded in bridge tokens.

## Compatibility guardrail

Phase 1 intentionally does not drop `uq_sales_cart_items_artwork`. The current `POST /cart/add` path does not yet write `variant_id`, so dropping the old unique key would allow duplicate artwork rows in a cart. Phase 3 should replace that unique key after runtime code always writes a non-null variant id.

## Later phases

Phase 2 updates the artwork admin page so artists can configure sale type, variants, inventory, and shipping.

Phase 3 updates public artwork pages, add-to-cart, cart review/edit, visible non-empty cart indicators, and cart bridge endpoints.

Phase 4 updates Stripe Checkout, order snapshots, shipping totals, payment completion, and variant inventory consumption.

Phase 5 replaces the current 12-hour and 24-hour abandoned-cart reminders with 1-day, 3-day, and 7-day reminders that only send when the cart owner is known and the cart still has available items.

## Verification

Run:

```bash
php -l scripts/test/sales_cart_phase1_static.php
php scripts/test/sales_cart_phase1_static.php
php scripts/database/migrate.php
php scripts/database/check_migration_integrity.php
./scripts/test/preflight.sh
```


## Phase 4 checkout finalization

Phase 4 makes checkout order creation variant-native. `SalesRepository::createOrderFromCart()` locks the selected `artwork_sale_variants` rows, checks live reservations by `variant_id`, snapshots variant label, size, fit, item shipping, and line shipping onto `sales_order_items`, and writes `sales_orders.shipping_cents` plus a buyer-facing total that includes shipping.

`StripeCheckoutService` still uses inline `price_data`; tenants do not need Stripe Products or Prices. Shipping is sent as an inline fixed `shipping_options[0][shipping_rate_data]` entry named **Standard shipping**, computed from ArtsFolio item-level and variant-level shipping configuration. The buyer email is passed to Stripe when known.

Stripe webhooks now consume inventory from `artwork_sale_variants`, not directly from `artworks`. After successful payment, ArtsFolio synchronizes the legacy artwork inventory/sold fields from active variants so older screens and reports stay coherent while variants remain the source of truth.

<!-- End of file. -->
<!-- End of file. -->

## Phase 2 admin persistence

Phase 2 introduces `App\Tenant\Sales\ArtworkSaleAdminForm`, a small helper used by both `ArtworkUploadController` and `ArtworksController`. It renders the tenant-admin **Sales & checkout** form controls and persists submitted data into the Phase 1 sale catalog tables.

The helper intentionally keeps legacy artwork columns synchronized:

- `artworks.price` remains the human-facing price string used by existing public rendering.
- `artworks.is_one_off` remains compatible with the current artwork-level cart path.
- `artworks.inventory_quantity` is kept as the simple quantity or the sum of active variant quantities.

For one-off and multiple-identical-item listings, the helper keeps one active `artwork_sale_variants` row named `Default`. For `variant_inventory`, the helper marks existing variants inactive first, then updates or inserts submitted active variant rows. This protects old cart/order references while allowing admins to remove choices from future purchases.

Phase 2 does not change public add-to-cart, Stripe checkout, inventory reservation, or abandoned cart behavior. Those runtime changes are intentionally held for later phases.

<!-- End of file. -->

## Phase 3 public cart runtime

Phase 3 makes the public cart variant-aware. Public artwork pages render active `artwork_sale_variants`, post `variant_id` to `/cart/add`, and show a non-empty cart indicator in tenant navigation. `CartIdentityService` resolves one canonical tenant cart across platform subdomains and custom domains through first-party host cookies plus signed cart bridge tokens. The `/cart/bridge-pixel` endpoint silently attaches alternate active tenant domains, and `/cart/bridge` is available for clicked links.

Checkout still uses the existing Stripe path in this phase. Shipping and variant snapshots are visible in the cart, but Stripe shipping finalization remains a Phase 4 responsibility.

