# ArtsFolio HTTP Routes

The in-application developer reference is available at `/help/developer` or `/developer` after login. It includes route descriptions and curl examples for browser authentication, public platform routes, platform admin routes, tenant public routes, and tenant admin routes.

Operational notes:

- Platform admin routes live under `/platform/admin/*` on the platform host.
- Tenant admin routes live under `/admin/*` on tenant hosts after tenant resolution.
- Browser form POST routes use CSRF tokens and should normally be exercised through the rendered form.
- Authenticated examples require the `artsfolio_session` browser cookie.
- The old platform `/admin/*` routes redirect to `/platform/admin/*` on the platform host for compatibility.

# End of file.
