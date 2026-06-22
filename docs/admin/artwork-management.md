# Artwork management

## Large catalogs

The tenant artwork list displays 50 artworks per page. Filters are available for search text, publication status, sale status, image presence, portfolio section, and sort order.

The placement matrix also displays 50 artworks per page. Saving a placement page updates only the artworks visible on that page. Assignments on other pages are preserved.

The public portfolio displays 24 published artworks per page and preserves the selected section while moving between pages.

## Artwork counts per page

The public portfolio, tenant artwork list, and Artwork Placement Matrix include an **Artworks per page** selector. Admin pages offer 10 through 100 in increments of 10 and default to 50. The public portfolio defaults to 24 and also offers 10-through-100 choices. Search, section, sort, and pagination links preserve the selected page size.

# End of file.

## Increment and decrement paging controls

The artwork list and placement matrix include **− Previous** and **Next +** controls in addition to numbered page links. These controls preserve all active search, filter, section, sort, and artworks-per-page query values. The unavailable direction is rendered disabled on the first or last page.

## In-place catalog paging

The tenant artwork list and artwork placement matrix use progressively enhanced paging. Previous, next, page-number, filter, and page-size requests replace only the artwork region while keeping the surrounding admin page in place. The browser URL and history are updated, so bookmarks and Back/Forward navigation remain correct. Ordinary GET links and forms remain available as the fallback when JavaScript is unavailable.
