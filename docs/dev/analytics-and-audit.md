# Analytics and audit wiring

Public tenant routes write analytics to `analytics_events`. The admin stats screens read from the same table.

Tracked public events:

- `page_view` for tenant home.
- `portfolio_view` for tenant portfolio.
- `about_view` for tenant about page.
- `contact_view` for tenant contact page.
- `image_view` for tenant artwork detail pages, with `entity_type = artwork` and `entity_id = artworks.id`.

Audit events are stored in `audit_log`. Tenant admin audit screens filter by `tenant_id`; platform audit screens can filter by tenant, user, and action.

Analytics writes are best-effort and must not break public page rendering. If an analytics insert fails, the public controller catches the exception and continues rendering.

# End of file.
