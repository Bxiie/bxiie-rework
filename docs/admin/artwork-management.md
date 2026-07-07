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

## Placement matrix column filters

The artwork placement matrix supports two complementary client-side filters:

- Type part of a portfolio-section name in **Visible columns** to hide nonmatching placement columns. Thumbnail and artwork identity columns remain visible.
- Select a portfolio-section or Home-page column heading to show only artworks currently assigned to that column on the visible page. Select the active heading again, or use **All artworks**, to clear the assignment filter.

**All columns** clears the column-name text filter. Column and assignment filters remain active while paging with the in-place artwork pager, and do not change saved placement data.

## Portfolio section actions

The Portfolio Sections page presents its three primary workflows as prominent action cards above the section list:

- **Add portfolio section** creates a new public artwork grouping.
- **Order artwork in sections and home page** controls the display order inside each destination.
- **Artwork placement matrix** assigns many artworks to sections in one view.

## 2026-07-07 artwork grid return state

Artwork grid edit links and the edit-page back link preserve the current grid URL, including filters, page number, sorting, and page size. Saving continues to return to the filtered grid and anchors to the edited artwork.

<!-- End of file. -->
