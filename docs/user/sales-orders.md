
## Buyer shipping details

When an artist marks an order as shipped, they can send shipping details to the buyer from ArtsFolio. The buyer receives an email with the order number, carrier, tracking number, tracking URL, and a summary of the purchased items. Buyers should reply to the email or contact the artist if the carrier or tracking number is missing.

# End of file.

## Viewing buyer shipping details

Open **Admin → Sales**, then open the order review page. The **Customer** section shows the buyer name, email, and formatted shipping address when Stripe returned one. If the buyer did not provide a shipping address, ArtsFolio says that no shipping address was recorded.

<!-- End of sales shipping display update. -->

<!-- sales-shipping-contact-20260708 -->
## Shipping address and phone at checkout

When an order includes shipping, the cart asks the buyer for a phone number and complete shipping address before payment. These details are saved with the order so the artist can prepare shipment and send tracking information.

# End of sales shipping contact user documentation.

### Checkout shipping details

When an order requires shipping, ArtsFolio asks for the shipping address and phone number before opening Stripe Checkout. Those details are passed to Stripe so the hosted payment page can reuse them instead of asking for the same shipping address again.

