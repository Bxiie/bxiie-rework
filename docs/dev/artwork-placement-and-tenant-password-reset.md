# Artwork placement and tenant-scoped password reset

`app/Http/Controllers/Tenant/Admin/ArtworkPlacementController.php` owns the alternate artwork placement matrix and section/home ordering screen.

Routes:

- `GET /admin/artworks/placement`
- `POST /admin/artworks/placement`
- `GET /admin/portfolio-sections/order`
- `POST /admin/portfolio-sections/order`

Ordering uses `homepage_artwork_assignments.sort_order` for the home page and `artwork_section_assignments.sort_order` for portfolio sections.

Tenant password reset requests in `public/index.php` call `tenantPasswordResetRecipientExists()` before creating a reset token. The guard accepts users attached to the current tenant through `tenant_memberships` or the legacy `tenant_users` table and deliberately does not send reset links to unrelated platform users or public mailing-list subscribers.

# End of file.
