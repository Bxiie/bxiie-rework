
## Engagement location and tenant user administration

Migration `0021_engagement_location_and_tenant_user_admin.sql` adds `country`, `region`, and `city` columns to `contact_messages` and `email_signups`. Public tenant submission controllers resolve location through `AnalyticsLocationResolver` and pass the values through the contact and signup services into persistence.

Tenant admin user management exposes POST routes for `/admin/users/promote-owner` and `/admin/users/delete`. Only tenant owners may call them. Deletes remove tenant-scoped role assignments and tenant membership, revoke the deleted user's active sessions, and record `tenant.user.deleted` in `audit_log`.

# End of file.
