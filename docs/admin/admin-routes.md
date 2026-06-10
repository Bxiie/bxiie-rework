# Admin routes

Tenant admin routes are available on a tenant domain after login:

- `/admin` - tenant dashboard.
- `/admin/stats` - tenant analytics for public page and artwork views.
- `/admin/contact-messages` - tenant contact form messages.
- `/admin/audit-log` - tenant-scoped audit events.

Platform admin routes are available on the platform host for platform staff:

- `/admin` - platform dashboard.
- `/admin/stats` - platform-wide analytics summary.
- `/admin/contact-messages` - contact messages across tenants.
- `/admin/audit-log` - platform-wide audit events.

If a route is tested on the wrong host, the visible data scope changes. Tenant routes depend on tenant resolution from the request host. Platform routes do not imply tenant context.

# End of file.
