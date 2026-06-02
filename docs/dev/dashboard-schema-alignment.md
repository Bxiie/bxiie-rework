# Dashboard schema alignment

Platform and tenant dashboards query the production ArtsFolio schema directly. The pricing plan table is `plans`; there is no `pricing_plans` table in production. Dashboard optional table and column checks use `SHOW TABLES` and `SHOW COLUMNS` so they work with the same MariaDB permissions used by the application.

When dashboard queries fail, platform and tenant admins see a concise query-failure message instead of misleading empty-state text such as "table is not installed." This keeps operational debugging honest during rolling deploys.

# End of file.
