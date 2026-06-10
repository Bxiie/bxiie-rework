INSERT INTO tenants (uuid, slug, name, status)
VALUES (UUID(), 'bxiie', 'Bxiie', 'active');

INSERT INTO tenant_domains (tenant_id, hostname, domain_type, status, is_primary)
SELECT id, 'bxiie.artsfol.io', 'platform_subdomain', 'active', FALSE
FROM tenants
WHERE slug = 'bxiie';

INSERT INTO tenant_domains (tenant_id, hostname, domain_type, status, is_primary)
SELECT id, 'bxiie.com', 'custom', 'active', TRUE
FROM tenants
WHERE slug = 'bxiie';

INSERT INTO tenant_domains (tenant_id, hostname, domain_type, status, is_primary)
SELECT id, 'www.bxiie.com', 'custom', 'active', FALSE
FROM tenants
WHERE slug = 'bxiie';

INSERT INTO tenant_plan_assignments (tenant_id, plan_id, status)
SELECT t.id, p.id, 'manual'
FROM tenants t
JOIN plans p ON p.slug = 'pro'
WHERE t.slug = 'bxiie';
