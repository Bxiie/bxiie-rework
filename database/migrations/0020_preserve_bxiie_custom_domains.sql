-- Preserve the canonical bxiie tenant custom-domain mappings.
--
-- This migration is intentionally non-destructive. It inserts missing bxiie
-- domain rows and re-activates rows that already belong to the bxiie tenant,
-- but it does not delete tenant_domains rows or steal hostnames assigned to
-- another tenant. If a hostname collision exists, preflight should fail so an
-- operator can resolve the data problem explicitly.

UPDATE tenants
SET status = 'active', updated_at = CURRENT_TIMESTAMP
WHERE slug = 'bxiie' AND status <> 'active';

INSERT INTO tenant_domains (tenant_id, hostname, domain_type, status, is_primary)
SELECT t.id, 'bxiie.artsfol.io', 'platform_subdomain', 'active', FALSE
FROM tenants t
WHERE t.slug = 'bxiie'
  AND NOT EXISTS (SELECT 1 FROM tenant_domains td WHERE td.hostname = 'bxiie.artsfol.io');

INSERT INTO tenant_domains (tenant_id, hostname, domain_type, status, is_primary)
SELECT t.id, 'bxiie.com', 'custom', 'active', TRUE
FROM tenants t
WHERE t.slug = 'bxiie'
  AND NOT EXISTS (SELECT 1 FROM tenant_domains td WHERE td.hostname = 'bxiie.com');

INSERT INTO tenant_domains (tenant_id, hostname, domain_type, status, is_primary)
SELECT t.id, 'www.bxiie.com', 'custom', 'active', FALSE
FROM tenants t
WHERE t.slug = 'bxiie'
  AND NOT EXISTS (SELECT 1 FROM tenant_domains td WHERE td.hostname = 'www.bxiie.com');

UPDATE tenant_domains td
JOIN tenants t ON t.id = td.tenant_id
SET td.status = 'active',
    td.domain_type = 'platform_subdomain',
    td.is_primary = FALSE,
    td.updated_at = CURRENT_TIMESTAMP
WHERE t.slug = 'bxiie'
  AND td.hostname = 'bxiie.artsfol.io';

UPDATE tenant_domains td
JOIN tenants t ON t.id = td.tenant_id
SET td.status = 'active',
    td.domain_type = 'custom',
    td.is_primary = TRUE,
    td.updated_at = CURRENT_TIMESTAMP
WHERE t.slug = 'bxiie'
  AND td.hostname = 'bxiie.com';

UPDATE tenant_domains td
JOIN tenants t ON t.id = td.tenant_id
SET td.status = 'active',
    td.domain_type = 'custom',
    td.is_primary = FALSE,
    td.updated_at = CURRENT_TIMESTAMP
WHERE t.slug = 'bxiie'
  AND td.hostname = 'www.bxiie.com';

-- End of file.
