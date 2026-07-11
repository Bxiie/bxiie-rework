# Onboarding state reset

The tenant-wide reset actions are:

- `POST /admin/onboarding/reset`
- `POST /platform/admin/tenants/onboarding/reset`

Both require administrative authorization and CSRF validation. The reset deletes tenant-scoped settings in the `onboarding_%`, `admin_onboarding_%`, `admin_tour_%`, `getting_started_%`, and `dashboard_checklist_%` namespaces.

The tenant dashboard redirect also performs best-effort cleanup of ArtsFolio local-storage keys containing onboarding, tour, or checklist.

Audit actions are `tenant.onboarding.reset` and `platform.tenant.onboarding_reset`.

<!-- End of file. -->
