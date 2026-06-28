# Tenant Site

## Logged-in preview switch

When signed in to a tenant site, the public footer includes a preview switch for showing or hiding unpublished portfolio sections and artwork images. Public visitors only see published content.

Curation controls appear on artwork detail pages instead of portfolio/home cards.

## Logged-in unpublished preview switch

Logged-in tenant users see a footer switch that toggles unpublished sections and artwork images with the `preview_unpublished=1` query parameter. Public visitors continue to see only published content.

## Unpublished preview switch

Tenant owners and admins see a footer switch on public tenant pages. The switch adds or removes `preview_unpublished=1`, which controls whether unpublished sections and artwork images are displayed. Public visitors and non-admin users only see published content.

## Persistent unpublished preview switch

Tenant owners and admins see a footer switch on public tenant pages. The switch saves each user's unpublished-preview preference for the current tenant and toggles `preview_unpublished=1` or `preview_unpublished=0` when clicked. Public visitors and non-admin users only see published content even if they add the query parameter manually.

## No-reload unpublished preview switch

Tenant owners and admins can toggle unpublished preview from the public footer without a browser reload. The switch saves the per-user tenant preference with a background request, refreshes the visible page content in place, and is not shown on About or Contact pages.

<!-- End of file. -->
