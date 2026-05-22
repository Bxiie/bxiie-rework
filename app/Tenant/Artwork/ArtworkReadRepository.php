<?php

declare(strict_types=1);

namespace App\Tenant\Artwork;

use App\Platform\Tenancy\TenantContext;
use PDO;

/**
 * Provides tenant-scoped artwork read operations for public rendering.
 */
final class ArtworkReadRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function findPublishedBySlug(TenantContext $tenant, string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.id, a.uuid, a.title, a.slug, a.description, a.medium, a.dimensions, a.year_created, a.status,
                    m.uuid AS media_uuid, m.alt_text AS media_alt_text
             FROM artworks a
             LEFT JOIN media_assets m ON m.id = a.primary_media_id
             WHERE a.tenant_id = :tenant_id
               AND a.slug = :slug
               AND a.status = 'published'
             LIMIT 1"
        );

        $stmt->execute([
            'tenant_id' => $tenant->tenantId,
            'slug' => $slug,
        ]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function activeSections(TenantContext $tenant): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, slug
             FROM portfolio_sections
             WHERE tenant_id = :tenant_id
               AND status = 'active'
               AND show_as_tab = 1
             ORDER BY sort_order ASC, name ASC"
        );

        $stmt->execute(['tenant_id' => $tenant->tenantId]);

        return $stmt->fetchAll();
    }

    public function publishedForSection(TenantContext $tenant, string $sectionSlug, int $limit = 240): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.id, a.uuid, a.title, a.slug, a.medium, a.dimensions, a.year_created, a.status,
                    m.uuid AS media_uuid, m.alt_text AS media_alt_text
             FROM artworks a
             JOIN artwork_section_assignments asa ON asa.artwork_id = a.id
             JOIN portfolio_sections ps ON ps.id = asa.section_id
             LEFT JOIN media_assets m ON m.id = a.primary_media_id
             WHERE a.tenant_id = :tenant_id
               AND a.status = 'published'
               AND ps.tenant_id = a.tenant_id
               AND ps.slug = :section_slug
               AND ps.status = 'active'
             ORDER BY asa.sort_order ASC, a.sort_order ASC, a.id DESC
             LIMIT :limit_count"
        );

        $stmt->bindValue('tenant_id', $tenant->tenantId, PDO::PARAM_INT);
        $stmt->bindValue('section_slug', $sectionSlug);
        $stmt->bindValue('limit_count', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function latestPublished(TenantContext $tenant, int $limit = 12): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.id, a.uuid, a.title, a.slug, a.medium, a.dimensions, a.year_created, a.status,
                    m.uuid AS media_uuid, m.alt_text AS media_alt_text
             FROM artworks a
             LEFT JOIN media_assets m ON m.id = a.primary_media_id
             WHERE a.tenant_id = :tenant_id
               AND a.status = 'published'
             ORDER BY a.sort_order ASC, a.id DESC
             LIMIT :limit_count"
        );

        $stmt->bindValue('tenant_id', $tenant->tenantId, PDO::PARAM_INT);
        $stmt->bindValue('limit_count', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}

// End of file.
