# Platform Admin

## Tenant search and actual paying tenants

The Platform Admin tenants page includes an in-page tenant search that filters the rendered tenant table by visible row values such as name, slug, UUID, status, plan, or domain.

The Platform Admin dashboard includes an Actual paying tenants count. This count is rendered from the dashboard view and counts active, non-complementary tenants on paid plans with confirmed Stripe subscription IDs.

<!-- End of file. -->

## Actual paying tenants dashboard metric

The Platform Admin dashboard shows Actual paying tenants using the existing dashboard metric flow. This excludes Free, complimentary, and unconfirmed checkout tenants by requiring a paid current plan and a confirmed Stripe subscription ID.

<!-- End of file. -->

## Actual paying tenant metrics

The Platform Admin dashboard and Billing Health page show Actual paying tenants. The metric counts active, non-complementary tenants assigned to paid plans with confirmed Stripe subscription IDs and active-ish billing states.

The Platform Admin tenants page includes an in-page tenant search that filters the rendered tenant table.

## Tenant search and drill-in

Platform Admin Tenants supports server-side all-tenant search. Search results exclude deleted tenants and tenant drill-in uses a direct tenant lookup so every visible search result opens consistently.

<!-- End of file. -->
