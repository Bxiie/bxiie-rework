<?php

declare(strict_types=1);

namespace App\Platform\Directory;

use PDO;
use Throwable;

/**
 * Maintains the denormalized public-directory projection.
 */
final class TenantDirectoryProfileRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function syncTenant(int $tenantId): void
    {
        if ($tenantId <= 0) {
            return;
        }

        try {
            $sql = <<<SQL
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
    END AS is_listed,
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
    ON opt.tenant_id = t.id
   AND opt.setting_key = 'platform_directory_opt_in'
LEFT JOIN tenant_settings summary
    ON summary.tenant_id = t.id
   AND summary.setting_key = 'platform_directory_summary'
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
        WHERE td.tenant_id = t.id
          AND td.status = 'active'
        ORDER BY td.is_primary DESC, td.id ASC
        LIMIT 1
    )
WHERE t.id = :tenant_id
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
    updated_at = CURRENT_TIMESTAMP
SQL;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['tenant_id' => $tenantId]);
        } catch (Throwable $e) {
            // During rolling deploys the projection table may not exist yet.
            error_log('ArtsFolio directory projection sync failed: ' . $e->getMessage());
        }
    }

    public function rebuildAll(): int
    {
        $tenantIds = $this->pdo->query("SELECT id FROM tenants WHERE status <> 'deleted' ORDER BY id")
            ->fetchAll(PDO::FETCH_COLUMN) ?: [];

        foreach ($tenantIds as $tenantId) {
            $this->syncTenant((int) $tenantId);
        }

        return count($tenantIds);
    }

    /** @return array<int, array<string, mixed>> */
    public function page(int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $this->pdo->prepare(
            "SELECT tenant_id, display_name, summary, thumbnail_media_uuid,
                    thumbnail_title, primary_hostname
             FROM tenant_directory_profiles
             WHERE is_listed = 1
             ORDER BY sort_name ASC, tenant_id ASC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listedCount(): int
    {
        return (int) $this->pdo->query(
            'SELECT COUNT(*) FROM tenant_directory_profiles WHERE is_listed = 1'
        )->fetchColumn();
    }
}

// End of file.
