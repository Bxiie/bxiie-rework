# Home Page virtual portfolio section

Home Page appears in tenant admin as a special portfolio section but is not
stored in `portfolio_sections`.

- Ordinary section membership uses `artwork_section_assignments`.
- Home Page membership and ordering use `homepage_artwork_assignments`.
- Only published, non-site artworks belonging to the current tenant may be saved.
- Replacement is role-protected, CSRF-protected, tenant-scoped, and transactional.

<!-- End of file. -->

Draft status is not rejected during assignment. Public rendering applies the same status visibility behavior used by ordinary portfolio views.
