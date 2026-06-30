# Buying artwork with the shopping cart

ArtsFolio carts are scoped to one artist at a time. A cart for one artist does not mix with a cart for another artist.

A cart can contain multiple items from the same artist. Some works are originals with only one available. Other items, such as prints or stickers, may allow a quantity greater than one. Clothing and similar items may ask for size or fit before adding to the cart.

When the cart is not empty, the artist site will show a cart link. The cart page lets the buyer review items, change quantities, remove items, continue browsing, or continue to Stripe Checkout.

If the artist has both an ArtsFolio subdomain and a custom domain, the cart should follow between those domains. Each domain stores its own secure cart cookie, and ArtsFolio connects those cookies to the same artist cart behind the scenes.

<!-- End of file. -->
