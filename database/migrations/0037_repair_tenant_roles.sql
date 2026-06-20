INSERT INTO roles (scope, slug, name, description)
VALUES
('platform', 'owner', 'Platform Owner', 'Full platform ownership.'),
('platform', 'admin', 'Platform Admin', 'Platform administration without ownership transfer.'),
('platform', 'support', 'Platform Support', 'Support access for tenant troubleshooting.'),
('platform', 'readonly', 'Platform Read Only', 'Read-only platform visibility.'),
('tenant', 'owner', 'Tenant Owner', 'Full tenant ownership.'),
('tenant', 'admin', 'Tenant Admin', 'Tenant administration including account and billing details.'),
('tenant', 'editor', 'Tenant Editor', 'Tenant content editing.'),
('tenant', 'viewer', 'Tenant Viewer', 'Read-only tenant access.')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description);
