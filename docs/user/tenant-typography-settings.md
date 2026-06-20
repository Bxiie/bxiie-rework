# Choosing site fonts and text sizes

Use Tenant Admin > Settings > Typography to change how text looks on your public site.

The controls affect the public home, portfolio, about, contact, artwork, forms, and footer areas.

## What you can change

You can choose fonts for:

- Body text
- Headings
- Site title
- Navigation
- Artwork titles
- Artwork details
- Forms
- Footer

You can choose sizes for the same public-site areas.

## How to use it

1. Open Tenant Admin > Settings.
2. Scroll to Typography.
3. Choose fonts from the picker.
4. Adjust sizes, such as `1rem`, `18px`, or `clamp(2.5rem, 8vw, 6rem)`.
5. Click any **Save site settings** button.
6. View the public site and refresh the page.

The font picker uses built-in browser/system fonts so your site stays fast and avoids external font downloads.

## Live preview and cache behavior

The font preview samples update immediately when a font picker or related size field changes. Public pages load `site.css` with a typography cache-bust query so saved font changes are not hidden by stale browser CSS.

# End of file.
