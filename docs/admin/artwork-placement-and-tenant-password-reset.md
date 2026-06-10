# Artwork placement and tenant password reset

Tenant administrators can use `/admin/artworks/placement` as an alternate artwork management page. The left column shows each artwork thumbnail, followed by checkboxes for home page placement and every active portfolio section.

Portfolio section artwork order is managed from `/admin/portfolio-sections/order`. The page includes the home page and every active portfolio section. Rows can be dragged, or numeric order fields can be edited directly. Lower numbers appear first.

Tenant password reset requests are scoped to the current tenant. A reset email is queued only when the submitted email belongs to a user with an active or invited membership for that tenant, using either `tenant_memberships` or the legacy `tenant_users` table.

# End of file.
