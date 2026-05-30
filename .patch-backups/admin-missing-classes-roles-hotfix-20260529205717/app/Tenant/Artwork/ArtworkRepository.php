<?php

declare(strict_types=1);

namespace App\Tenant\Artwork;

use App\Platform\Tenancy\TenantContext;
use App\Support\Uuid;
use PDO;

/**
 * Handles database persistence for tenant artwork records.
 */
final class ArtworkRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function create(
        TenantContext $tenant,
        string $title,
        string $slug,
        ?int $primaryMediaId = null,
        ?string $description = null,
        ?string $medium = null,
        ?string $dimensions = null,
        ?string $yearCreated = null,
        string $status = 'draft',
        int $sortOrder = 0,
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO artworks (
                uuid,
                tenant_id,
                primary_media_id,
                title,
                slug,
                description,
                medium,
                dimensions,
                year_created,
                status,
                sort_order
            ) VALUES (
                :uuid,
                :tenant_id,
                :primary_media_id,
                :title,
                :slug,
                :description,
                :medium,
                :dimensions,
                :year_created,
                :status,
                :sort_order
            )"
        );

        $stmt->execute([
            'uuid' => Uuid::v4(),
            'tenant_id' => $tenant->tenantId,
            'primary_media_id' => $primaryMediaId,
            'title' => $title,
            'slug' => $slug,
            'description' => $description,
            'medium' => $medium,
            'dimensions' => $dimensions,
            'year_created' => $yearCreated,
            'status' => $status,
            'sort_order' => $sortOrder,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}

// End of file.
