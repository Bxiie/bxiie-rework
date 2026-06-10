# Tenant public admin link

Tenant public pages must link signed-in users to `/admin` on the current tenant host. Platform admin routes belong to the canonical platform host under `/platform/admin` and must not be emitted in tenant public navigation.

The tenant public-site layout uses `Tenant\HomeController::tenantAdminLink()` for this behavior. Platform chrome helpers are only for platform pages.

# End of file.
