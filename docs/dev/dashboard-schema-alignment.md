# Dashboard schema alignment

The platform and tenant dashboards read directly from the migrated ArtsFolio MariaDB schema. Dashboard table and column capability checks use `SHOW TABLES LIKE` and `SHOW COLUMNS` rather than `information_schema` because production grants can make information-schema probes return false negatives.

Dashboard code should not report "table is not installed" when the migrations are present. If a query fails, fix the query/schema mismatch instead of hiding it behind an install message.

Dashboard sales metrics include gross sales, platform commission, credit-card fee estimates, and seller net so platform and tenant analytics use the same sales economics shown in pricing and billing.

# End of file.
