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
            "SELECT id, uuid, title, slug, description, medium, dimensions, year_created, status
             FROM artworks
             WHERE tenant_id = :tenant_id
               AND slug = :slug
               AND status = 'published'
             LIMIT 1"
        );

        $stmt->execute([
            'tenant_id' => $tenant->tenantId,
            'slug' => $slug,
        ]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function latestPublished(TenantContext $tenant, int $limit = 12): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, uuid, title, slug, medium, dimensions, year_created, status
             FROM artworks
             WHERE tenant_id = :tenant_id
               AND status = 'published'
             ORDER BY sort_order ASC, id DESC
             LIMIT :limit_count"
        );

        $stmt->bindValue('tenant_id', $tenant->tenantId, PDO::PARAM_INT);
        $stmt->bindValue('limit_count', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}

// End of file.
