# Tenant directory opt-in

Tenant admins control public directory participation from `/admin/directory` on the tenant domain.

## Behavior

- Tenants are hidden from the public ArtsFolio directory by default.
- Tenant admins must check **Show this tenant in the public ArtsFolio directory**.
- Platform admins must also enable the platform-wide directory setting before public directory listings appear.
- Directory summaries are stored in tenant settings under `platform_directory_summary`.
- Opt-in state is stored in tenant settings under `platform_directory_opt_in`.

## Verification

1. Sign into the tenant domain as a tenant admin.
2. Open `/admin/directory`.
3. Enable the directory listing and add a short summary.
4. Save.
5. Confirm redirect to `/admin/directory?notice=saved`.
6. Confirm the tenant can appear on `https://artsfol.io/directory` when the platform directory is enabled.

<!-- End of file. -->
