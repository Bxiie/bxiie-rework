<?php

declare(strict_types=1);

namespace App\Tenant\Portfolio;

use App\Platform\Tenancy\TenantContext;
use App\Support\Uuid;
use PDO;

/**
 * Handles tenant portfolio section persistence and artwork-section assignment.
 */
final class PortfolioSectionRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function create(
        TenantContext $tenant,
        string $name,
        string $slug,
        ?string $description = null,
        bool $showAsTab = false,
        int $sortOrder = 0,
        string $status = 'active',
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO portfolio_sections (
                uuid,
                tenant_id,
                name,
                slug,
                description,
                show_as_tab,
                sort_order,
                status
            ) VALUES (
                :uuid,
                :tenant_id,
                :name,
                :slug,
                :description,
                :show_as_tab,
                :sort_order,
                :status
            )"
        );

        $stmt->execute([
            'uuid' => Uuid::v4(),
            'tenant_id' => $tenant->tenantId,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'show_as_tab' => $showAsTab ? 1 : 0,
            'sort_order' => $sortOrder,
            'status' => $status,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function assignArtwork(int $artworkId, int $sectionId, int $sortOrder = 0): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO artwork_section_assignments (
                artwork_id,
                section_id,
                sort_order
            ) VALUES (
                :artwork_id,
                :section_id,
                :sort_order
            )
            ON DUPLICATE KEY UPDATE
                sort_order = VALUES(sort_order)"
        );

        $stmt->execute([
            'artwork_id' => $artworkId,
            'section_id' => $sectionId,
            'sort_order' => $sortOrder,
        ]);
    }
}

// End of file.
