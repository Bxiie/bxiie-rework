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
    public function __construct(private readonly PDO $pdo) {}

    public function findPublishedBySlug(TenantContext $tenant, string $slug, bool $includeUnpublished = false): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.id, a.uuid, a.title, a.slug, a.description, a.medium, a.dimensions, a.year_created, a.status,
                    a.sale_status, a.price,
                    COALESCE(a.is_one_off, 1) AS is_one_off,
                    COALESCE(a.inventory_quantity, 1) AS inventory_quantity,
                    m.uuid AS media_uuid, m.alt_text AS media_alt_text
             FROM artworks a
             LEFT JOIN media_assets m ON m.id = a.primary_media_id
             WHERE a.tenant_id = :tenant_id
               AND a.slug = :slug
               AND (a.status = 'published' OR :include_unpublished = 1)
               AND " . $this->portfolioTypeExistsSql('a') . "
             LIMIT 1"
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'slug' => $slug, 'include_unpublished' => $includeUnpublished ? 1 : 0]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function latestPublished(TenantContext $tenant, int $limit = 12, bool $includeUnpublished = false): array
    {
        if ($this->tableExists('homepage_artwork_assignments')) {
            $stmt = $this->pdo->prepare(
                "SELECT a.id, a.uuid, a.title, a.slug, a.medium, a.dimensions, a.year_created, a.status,
                        a.sale_status, a.price,
                        COALESCE(a.is_one_off, 1) AS is_one_off,
                        COALESCE(a.inventory_quantity, 1) AS inventory_quantity,
                        m.uuid AS media_uuid, m.alt_text AS media_alt_text
                 FROM homepage_artwork_assignments h
                 JOIN artworks a ON a.id = h.artwork_id
                 LEFT JOIN media_assets m ON m.id = a.primary_media_id
                 WHERE h.tenant_id = :tenant_id
                   AND a.tenant_id = :tenant_id
                   AND (a.status = 'published' OR :include_unpublished = 1)
                   AND " . $this->portfolioTypeExistsSql('a') . "
                 ORDER BY h.sort_order ASC, a.sort_order ASC, a.id DESC
                 LIMIT :limit_count"
            );
            $stmt->bindValue('tenant_id', $tenant->tenantId, PDO::PARAM_INT);
            $stmt->bindValue('include_unpublished', $includeUnpublished ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue('limit_count', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        // Older databases may not have the home assignment table yet. In that
        // compatibility case only, preserve the legacy latest-published fallback.
        return $this->publishedOrdered($tenant, $limit, 'manual', $includeUnpublished);
    }

    /**
     * Returns published artwork using the tenant-selected display ordering.
     */
    public function publishedOrdered(TenantContext $tenant, int $limit = 240, string $order = 'date_desc', bool $includeUnpublished = false): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.id, a.uuid, a.title, a.slug, a.medium, a.dimensions, a.year_created, a.status,
                    a.sale_status, a.price,
                    COALESCE(a.is_one_off, 1) AS is_one_off,
                    COALESCE(a.inventory_quantity, 1) AS inventory_quantity,
                    m.uuid AS media_uuid, m.alt_text AS media_alt_text
             FROM artworks a
             LEFT JOIN media_assets m ON m.id = a.primary_media_id
             WHERE a.tenant_id = :tenant_id
               AND (a.status = 'published' OR :include_unpublished = 1)
               AND " . $this->portfolioTypeExistsSql('a') . "
             ORDER BY " . $this->orderSql($order) . "
             LIMIT :limit_count"
        );
        $stmt->bindValue('tenant_id', $tenant->tenantId, PDO::PARAM_INT);
        $stmt->bindValue('include_unpublished', $includeUnpublished ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue('limit_count', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }


    /**
     * Returns one public portfolio page and the total count using the same filters.
     */
    public function publishedPage(
        TenantContext $tenant,
        int $page,
        int $pageSize,
        string $order = 'date_desc',
        ?string $sectionSlug = null,
        bool $includeUnpublished = false,
    ): array {
        $page = max(1, $page);
        $pageSize = max(1, min(96, $pageSize));
        $offset = ($page - 1) * $pageSize;
        $sectionSlug = $sectionSlug !== null ? trim($sectionSlug) : null;

        $joins = '';
        $where = "a.tenant_id = :tenant_id AND (a.status = 'published' OR :include_unpublished = 1) AND " . $this->portfolioTypeExistsSql('a');
        $params = ['tenant_id' => $tenant->tenantId, 'include_unpublished' => $includeUnpublished ? 1 : 0];
        $manualDefault = 'a.sort_order ASC, a.id DESC';

        if ($sectionSlug !== null && $sectionSlug !== '') {
            $joins = ' JOIN artwork_section_assignments asa ON asa.artwork_id = a.id
'
                . ' JOIN portfolio_sections ps ON ps.id = asa.section_id';
            $where .= " AND ps.tenant_id = a.tenant_id AND ps.slug = :section_slug AND ps.status = 'active'";
            $params['section_slug'] = $sectionSlug;
            $manualDefault = 'asa.sort_order ASC, a.sort_order ASC, a.id DESC';
        }

        $count = $this->pdo->prepare(
            "SELECT COUNT(*) FROM artworks a{$joins} WHERE {$where}"
        );
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT a.id, a.uuid, a.title, a.slug, a.medium, a.dimensions, a.year_created, a.status,
                    a.sale_status, a.price,
                    COALESCE(a.is_one_off, 1) AS is_one_off,
                    COALESCE(a.inventory_quantity, 1) AS inventory_quantity,
                    m.uuid AS media_uuid, m.alt_text AS media_alt_text
             FROM artworks a{$joins}
             LEFT JOIN media_assets m ON m.id = a.primary_media_id
             WHERE {$where}
             ORDER BY " . $this->orderSql($order, $manualDefault) . "
             LIMIT :limit_count OFFSET :offset_count"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, in_array($key, ['tenant_id', 'include_unpublished'], true) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue('limit_count', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue('offset_count', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'page_count' => max(1, (int) ceil($total / $pageSize)),
        ];
    }

    public function activeSections(TenantContext $tenant, bool $includeUnpublished = false): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, slug
             FROM portfolio_sections
             WHERE tenant_id = :tenant_id
               AND status = 'active'
               AND show_as_tab = 1
               AND (:include_unpublished = 1 OR EXISTS (SELECT 1 FROM artwork_section_assignments asa JOIN artworks a ON a.id=asa.artwork_id WHERE asa.section_id=portfolio_sections.id AND a.status='published'))
             ORDER BY sort_order ASC, name ASC"
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'include_unpublished' => $includeUnpublished ? 1 : 0]);

        return $stmt->fetchAll();
    }

    public function publishedForSection(TenantContext $tenant, string $sectionSlug, int $limit = 240, string $order = 'manual'): array
    {
        $manualDefault = 'asa.sort_order ASC, a.sort_order ASC, a.id DESC';
        $stmt = $this->pdo->prepare(
            "SELECT a.id, a.uuid, a.title, a.slug, a.medium, a.dimensions, a.year_created, a.status,
                    a.sale_status, a.price,
                    COALESCE(a.is_one_off, 1) AS is_one_off,
                    COALESCE(a.inventory_quantity, 1) AS inventory_quantity,
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
               AND " . $this->portfolioTypeExistsSql('a') . "
             ORDER BY " . $this->orderSql($order, $manualDefault) . "
             LIMIT :limit_count"
        );
        $stmt->bindValue('tenant_id', $tenant->tenantId, PDO::PARAM_INT);
        $stmt->bindValue('section_slug', $sectionSlug);
        $stmt->bindValue('limit_count', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Keeps site-only images out of public portfolio routes while preserving legacy data.
     */
    private function portfolioTypeExistsSql(string $alias): string
    {
        return "EXISTS (
            SELECT 1
            FROM artwork_type_assignments ata
            JOIN artwork_types atype ON atype.id = ata.type_id
            WHERE ata.artwork_id = {$alias}.id
              AND atype.code = 'portfolio_images'
        )";
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name");
        $stmt->execute(['table_name' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Maps tenant artwork display preferences to safe SQL fragments.
     */
    private function orderSql(string $order, string $manualDefault = 'a.sort_order ASC, a.id DESC'): string
    {
        return match ($order) {
            'name' => 'a.title ASC, a.id ASC',
            'date' => "CAST(NULLIF(a.year_created, '') AS UNSIGNED) ASC, a.title ASC, a.id ASC",
            'date_desc' => "CAST(NULLIF(a.year_created, '') AS UNSIGNED) DESC, a.title ASC, a.id ASC",
            'medium' => 'a.medium ASC, a.title ASC, a.id ASC',
            'manual' => $manualDefault,
            default => 'a.created_at DESC, a.id DESC',
        };
    }
}

// End of file.
