CREATE TABLE IF NOT EXISTS tenant_directory_profiles (
    tenant_id BIGINT UNSIGNED NOT NULL,
    is_listed TINYINT(1) NOT NULL DEFAULT 0,
    display_name VARCHAR(190) NOT NULL,
    summary TEXT NULL,
    thumbnail_artwork_id BIGINT UNSIGNED NULL,
    thumbnail_media_id BIGINT UNSIGNED NULL,
    thumbnail_media_uuid CHAR(36) NULL,
    thumbnail_title VARCHAR(255) NULL,
    primary_hostname VARCHAR(255) NULL,
    sort_name VARCHAR(190) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (tenant_id),
    KEY idx_tenant_directory_profiles_listed_sort (is_listed, sort_name, tenant_id),
    KEY idx_tenant_directory_profiles_hostname (primary_hostname),
    CONSTRAINT tenant_directory_profiles_tenant_fk
        FOREIGN KEY (tenant_id) REFERENCES tenants(id)
        ON DELETE CASCADE,
    CONSTRAINT tenant_directory_profiles_artwork_fk
        FOREIGN KEY (thumbnail_artwork_id) REFERENCES artworks(id)
        ON DELETE SET NULL,
    CONSTRAINT tenant_directory_profiles_media_fk
        FOREIGN KEY (thumbnail_media_id) REFERENCES media_assets(id)
        ON DELETE SET NULL
);

INSERT INTO tenant_directory_profiles (
    tenant_id,
    is_listed,
    display_name,
    summary,
    thumbnail_artwork_id,
    thumbnail_media_id,
    thumbnail_media_uuid,
    thumbnail_title,
    primary_hostname,
    sort_name,
    updated_at
)
SELECT
    t.id,
    CASE
        WHEN t.status = 'active'
         AND LOWER(TRIM(COALESCE(opt.setting_value, '0'))) IN ('1', 'true', 'yes', 'on')
        THEN 1 ELSE 0
    END,
    t.name,
    COALESCE(summary.setting_value, ''),
    thumbnail_artwork.id,
    thumbnail_media.id,
    thumbnail_media.uuid,
    thumbnail_artwork.title,
    COALESCE(primary_domain.hostname, fallback_domain.hostname, CONCAT(t.slug, '.artsfol.io')),
    LOWER(TRIM(t.name)),
    CURRENT_TIMESTAMP
FROM tenants t
LEFT JOIN tenant_settings opt
    ON opt.tenant_id = t.id AND opt.setting_key = 'platform_directory_opt_in'
LEFT JOIN tenant_settings summary
    ON summary.tenant_id = t.id AND summary.setting_key = 'platform_directory_summary'
LEFT JOIN tenant_settings selected_thumbnail
    ON selected_thumbnail.tenant_id = t.id
   AND selected_thumbnail.setting_key = 'platform_directory_thumbnail_artwork_id'
LEFT JOIN artworks thumbnail_artwork
    ON thumbnail_artwork.tenant_id = t.id
   AND thumbnail_artwork.id = CAST(NULLIF(selected_thumbnail.setting_value, '') AS UNSIGNED)
   AND thumbnail_artwork.status = 'published'
LEFT JOIN media_assets thumbnail_media
    ON thumbnail_media.id = thumbnail_artwork.primary_media_id
   AND thumbnail_media.is_private = 0
LEFT JOIN tenant_domains primary_domain
    ON primary_domain.tenant_id = t.id
   AND primary_domain.is_primary = TRUE
   AND primary_domain.status = 'active'
LEFT JOIN tenant_domains fallback_domain
    ON fallback_domain.id = (
        SELECT td.id
        FROM tenant_domains td
        WHERE td.tenant_id = t.id AND td.status = 'active'
        ORDER BY td.is_primary DESC, td.id ASC
        LIMIT 1
    )
WHERE t.status <> 'deleted'
ON DUPLICATE KEY UPDATE
    is_listed = VALUES(is_listed),
    display_name = VALUES(display_name),
    summary = VALUES(summary),
    thumbnail_artwork_id = VALUES(thumbnail_artwork_id),
    thumbnail_media_id = VALUES(thumbnail_media_id),
    thumbnail_media_uuid = VALUES(thumbnail_media_uuid),
    thumbnail_title = VALUES(thumbnail_title),
    primary_hostname = VALUES(primary_hostname),
    sort_name = VALUES(sort_name),
    updated_at = CURRENT_TIMESTAMP;
