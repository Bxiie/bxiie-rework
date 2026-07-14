-- Repair new-tenant CSS values seeded under the unused custom_css key.
--
-- The Tenant Admin editor and public TenantCssController both use tenant_css.
-- Copy the documented seed into tenant_css only when no meaningful tenant_css
-- value already exists, then remove the orphaned custom_css row.

INSERT INTO tenant_settings (
    tenant_id,
    setting_key,
    setting_value,
    created_at,
    updated_at
)
SELECT
    source.tenant_id,
    'tenant_css',
    source.setting_value,
    COALESCE(source.created_at, CURRENT_TIMESTAMP),
    CURRENT_TIMESTAMP
FROM tenant_settings source
LEFT JOIN tenant_settings target
    ON target.tenant_id = source.tenant_id
   AND target.setting_key = 'tenant_css'
WHERE source.setting_key = 'custom_css'
  AND NULLIF(TRIM(COALESCE(source.setting_value, '')), '') IS NOT NULL
  AND (
      target.id IS NULL
      OR NULLIF(TRIM(COALESCE(target.setting_value, '')), '') IS NULL
  )
ON DUPLICATE KEY UPDATE
    setting_value = IF(
        NULLIF(TRIM(COALESCE(tenant_settings.setting_value, '')), '') IS NULL,
        VALUES(setting_value),
        tenant_settings.setting_value
    ),
    updated_at = IF(
        NULLIF(TRIM(COALESCE(tenant_settings.setting_value, '')), '') IS NULL,
        CURRENT_TIMESTAMP,
        tenant_settings.updated_at
    );

DELETE orphan
FROM tenant_settings orphan
WHERE orphan.setting_key = 'custom_css'
  AND EXISTS (
      SELECT 1
      FROM tenant_settings canonical
      WHERE canonical.tenant_id = orphan.tenant_id
        AND canonical.setting_key = 'tenant_css'
        AND NULLIF(TRIM(COALESCE(canonical.setting_value, '')), '') IS NOT NULL
  );

-- End of file.
