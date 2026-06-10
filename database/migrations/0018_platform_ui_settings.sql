-- Platform UI/settings defaults added by the 2026-05-30 admin polish patch.

INSERT INTO platform_settings (setting_key, setting_value, updated_at)
VALUES
    ('platform_footer_copyright_html', '© {year} ArtsFolio', CURRENT_TIMESTAMP),
    ('platform_directory_thumbnail_size', '180', CURRENT_TIMESTAMP),
    ('google_oauth_client_id', '', CURRENT_TIMESTAMP),
    ('google_oauth_client_secret', '', CURRENT_TIMESTAMP),
    ('facebook_oauth_client_id', '', CURRENT_TIMESTAMP),
    ('facebook_oauth_client_secret', '', CURRENT_TIMESTAMP),
    ('recaptcha_site_key', '', CURRENT_TIMESTAMP),
    ('recaptcha_secret_key', '', CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE setting_value = setting_value;

INSERT INTO platform_settings (setting_key, setting_value, updated_at)
VALUES ('platform_custom_css', '/* Platform custom CSS.\n   Edit from Platform Admin → Platform Settings. */\n\n:root {\n    --platform-accent: #c9a85f;\n}\n', CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE
    setting_value = CASE WHEN COALESCE(setting_value, '') = '' THEN VALUES(setting_value) ELSE setting_value END,
    updated_at = CURRENT_TIMESTAMP;

INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, updated_at)
SELECT id, 'tenant_css', '/* Tenant custom CSS.\n   Edit from Tenant Admin → Settings. */\n\n:root {\n    --tenant-accent: #c9a85f;\n}\n', CURRENT_TIMESTAMP
FROM tenants
WHERE NOT EXISTS (
    SELECT 1
    FROM tenant_settings ts
    WHERE ts.tenant_id = tenants.id
      AND ts.setting_key = 'tenant_css'
);

INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, updated_at)
SELECT id, 'artwork_display_order', 'date_desc', CURRENT_TIMESTAMP
FROM tenants
WHERE NOT EXISTS (
    SELECT 1
    FROM tenant_settings ts
    WHERE ts.tenant_id = tenants.id
      AND ts.setting_key = 'artwork_display_order'
);

-- End of file.
