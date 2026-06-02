# Sales Phase 3 Analytics

Sales phase 3 adds tenant and platform sales analytics on top of the Stripe checkout and workflow tables created in phase 2.

## Tenant admin

Tenant owners and tenant admins can open `/admin/sales/analytics` to review paid-order totals, gross sales, platform commission, average order value, workflow counts, daily sales, and best-selling artworks.

## Platform admin

Platform admins can open `/platform/admin/sales/analytics` to review platform-wide paid orders, selling tenants, gross sales, commission, workflow counts, daily sales, and sales grouped by tenant.

## Operational notes

Analytics read from existing `sales_orders` and `sales_order_items` rows. No new schema is required for this phase. Paid-sales analytics only count orders where `payment_status = "paid"`, while workflow counts include all workflow states so pending operational work remains visible.

<!-- End of file. -->
