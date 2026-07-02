# Buying artwork with the shopping cart

ArtsFolio carts are scoped to one artist at a time. A cart for one artist does not mix with a cart for another artist.

A cart can contain multiple items from the same artist. Some works are originals with only one available. Other items, such as prints or stickers, may allow a quantity greater than one. Clothing and similar items may ask for size or fit before adding to the cart.

When the cart is not empty, the artist site will show a cart link. The cart page lets the buyer review items, change quantities, remove items, continue browsing, or continue to Stripe Checkout.

If the artist has both an ArtsFolio subdomain and a custom domain, the cart should follow between those domains. Each domain stores its own secure cart cookie, and ArtsFolio connects those cookies to the same artist cart behind the scenes.


## Checkout and shipping

At checkout, ArtsFolio sends your selected items, sizes, quantities, and standard shipping total to Stripe Checkout. Stripe securely collects payment. The artist receives the order with the selected variant details, shipping information, and payment status.

If an item sells out while you are checking out, ArtsFolio may ask you to return to the cart and update your selection.

<!-- End of file. -->
<!-- End of file. -->

## Phase 2 status

Artists can now configure future shopping-cart details in the admin area, including one-off artwork, multiple inventory items, sized variants, gender/fit options, and shipping defaults. Buyer-facing size selection and variant-aware checkout will appear in later phases.

<!-- End of file. -->

## Buying sized or optioned artwork

Some artworks may ask you to choose a size, fit, edition, or other option before adding the item to your cart. Choose a size or option, set the quantity when available, and select Add to cart. The cart link appears in the site navigation whenever your cart has items.

If the artist uses both an ArtsFolio address and a custom domain, your cart follows between those addresses automatically.

## Saved cart reminders

If you enter your email on the cart page and leave before checkout, ArtsFolio may send a saved cart reminder. The reminder link can restore your cart even if the artist uses both an ArtsFolio subdomain and a custom domain.

A saved cart reminder does not reserve the artwork forever. Originals, sizes, prints, and other limited items may sell out before you return. The cart page will show the current state before checkout.


## Shared shipping profiles

Some products share shipping. For example, several stickers or small prints may travel together in one envelope, so checkout can charge one shared shipping amount instead of one shipping charge per item. The cart shows the total shipping calculated by the artist's selected shipping profile.

## Shipping profiles

Some small products share shipping. For example, stickers, postcards, patches, and other light items may use the **Small flat items** shipping profile. When several products in the cart use the same shared shipping profile, ArtsFolio groups them together for shipping instead of charging a separate shipping fee for every product.

Example: if ten different sticker products each cost $2.99 and all use **Small flat items**, the cart can charge one $5.00 shipping amount for that profile instead of $5.00 per sticker.

Large, fragile, installed, or freight-shipped artwork may use a quoted-shipping profile. Those items may require contacting the studio before checkout can be completed.

# End of file.
