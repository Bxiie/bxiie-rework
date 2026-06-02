# Platform pricing administration

Platform owners and platform admins manage public plan details from **Platform Admin → Plans & Billing**.

The pricing editor controls:

- public monthly price per plan;
- allowed artwork records per plan;
- allowed email addresses per plan;
- custom-domain inclusion;
- active/inactive display status;
- public display order;
- plan description;
- platform sales commission percentage.

The public `/pricing` page reads these values from the `plans` table and `platform_settings.platform_sales_commission_basis_points` so prospective tenants see the same limits and commission used by admin and tenant billing pages.

Free plans include an ArtsFolio notification/link on tenant public pages. This is intentional and should be reflected in public plan copy.

# End of file.
