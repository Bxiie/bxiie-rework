<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Http\View\ErrorPage;
use App\Platform\Tenancy\TenantContext;
use App\Support\Pagination\Pagination;
use PDO;

/**
 * Provides matrix-style artwork placement and ordering tools for tenant admins.
 */
final class ArtworkPlacementController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly PDO $pdo,
    ) {
    }

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser, $tenant)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        $q = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $pageSize = Pagination::allowedLimitFromQuery(
            $_GET['per_page'] ?? null,
            50,
            Pagination::standardPageSizes(),
        );
        $result = $this->artworksPage($tenant, $q, $page, $pageSize);
        $sections = $this->sections($tenant);
        $artworks = $result['items'];
        $visibleIds = array_map('intval', array_column($artworks, 'id'));
        $assignments = $this->sectionAssignmentsForArtworks($tenant, $visibleIds);
        $homeAssignments = $this->homeAssignmentsForArtworks($tenant, $visibleIds);

        $sectionHeaders = '';
        foreach ($sections as $section) {
            $sectionId = (int) $section['id'];
            $sectionName = $this->e((string) $section['name']);
            $sectionHeaders .= '<th scope="col" data-placement-column data-placement-column-name="' . $sectionName . '"><button type="button" class="placement-column-filter" data-placement-assignment-filter="section-' . $sectionId . '" aria-pressed="false" title="Show only artworks assigned to ' . $sectionName . '">' . $sectionName . '</button></th>';
        }

        $rows = '';
        foreach ($artworks as $artwork) {
            $id = (int) $artwork['id'];
            $title = $this->e((string) $artwork['title']);
            $status = $this->e((string) $artwork['status']);
            $thumb = $this->thumbnailHtml($artwork, $title, 112, 84);
            $homeChecked = isset($homeAssignments[$id]) ? ' checked' : '';
            $cells = '<td class="placement-check" data-placement-column data-placement-column-name="Home page" data-placement-assignment="home"><label><input type="checkbox" name="home_artwork_ids[]" value="' . $id . '"' . $homeChecked . '> Home</label></td>';
            foreach ($sections as $section) {
                $sectionId = (int) $section['id'];
                $sectionName = $this->e((string) $section['name']);
                $checked = isset($assignments[$id][$sectionId]) ? ' checked' : '';
                $cells .= '<td class="placement-check" data-placement-column data-placement-column-name="' . $sectionName . '" data-placement-assignment="section-' . $sectionId . '"><label><input type="checkbox" name="sections[' . $id . '][]" value="' . $sectionId . '"' . $checked . '> ' . $sectionName . '</label></td>';
            }
            $rows .= '<tr><td>' . $thumb . '</td><td><strong>' . $title . '</strong><br><small>ID ' . $id . ' · ' . $status . '</small><input type="hidden" name="visible_artwork_ids[]" value="' . $id . '"></td>' . $cells . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="' . (3 + count($sections)) . '">No artworks found.</td></tr>';
        }

        $base = array_filter([
            'q' => $q,
            'per_page' => $pageSize,
        ], static fn ($v): bool => $v !== '');
        $pageSizeOptions = '';
        foreach (Pagination::standardPageSizes() as $sizeOption) {
            $selected = $sizeOption === $pageSize ? ' selected' : '';
            $label = $sizeOption === 50 ? '50 (default)' : (string) $sizeOption;
            $pageSizeOptions .= '<option value="' . $sizeOption . '"' . $selected . '>' . $label . '</option>';
        }
        $pager = $this->pager('/admin/artworks/placement', $base, (int) $result['page'], (int) $result['page_count']);
        $notice = ($_GET['notice'] ?? '') === 'saved' ? '<p class="notice">Artwork placements saved for this page.</p>' : '';
        $summary = (int) $result['total'] === 0 ? 'No artworks' : 'Showing ' . (((int) $result['page'] - 1) * $pageSize + 1) . '–' . min((int) $result['page'] * $pageSize, (int) $result['total']) . ' of ' . (int) $result['total'];
        $returnTo = '/admin/artworks/placement?' . http_build_query(array_merge($base, ['page' => (int) $result['page']]));
        $body = <<<HTML
<main class="admin-main" style="max-width:1280px;margin:2rem auto;padding:0 1rem;">
<p><a href="/admin/artworks">&larr; Artworks</a> · <a href="/admin/portfolio-sections">Portfolio sections</a> · <a href="/admin/portfolio-sections/order">Order section artwork</a></p>
<h1>Artwork Placement Matrix</h1>
<p>Choose 10–100 artworks per page. Saving changes updates only the artworks shown on the current page and preserves assignments on every other page.</p>
{$notice}
<section data-artwork-pager-root tabindex="-1">
<form data-artwork-page-form method="get" action="/admin/artworks/placement" style="display:flex;gap:.75rem;align-items:end;flex-wrap:wrap;"><label>Search artworks<br><input type="search" name="q" value="{$this->e($q)}"></label><label>Artworks per page<br><select name="per_page">{$pageSizeOptions}</select></label><button type="submit">Apply</button><a href="/admin/artworks/placement">Clear</a></form>
<div class="placement-column-tools" style="display:flex;gap:.75rem;align-items:end;flex-wrap:wrap;margin:1rem 0;"><label>Visible columns<br><input type="search" data-placement-column-search placeholder="Type a column name" autocomplete="off"></label><button type="button" data-placement-column-reset>All columns</button><button type="button" data-placement-assignment-reset hidden>All artworks</button><span data-placement-filter-status role="status" aria-live="polite"></span></div>
<p><strong>{$summary}</strong></p>{$pager}
<form method="post" action="/admin/artworks/placement"><input type="hidden" name="return_to" value="{$this->e($returnTo)}">
<div style="overflow-x:auto;"><table class="placement-matrix" data-placement-matrix border="1" cellpadding="8" cellspacing="0" style="width:100%;border-collapse:collapse;"><thead><tr><th>Thumbnail</th><th>Artwork</th><th data-placement-column data-placement-column-name="Home page"><button type="button" class="placement-column-filter" data-placement-assignment-filter="home" aria-pressed="false" title="Show only artworks assigned to Home page">Home page</button></th>{$sectionHeaders}</tr></thead><tbody>{$rows}</tbody></table></div>
<p><button type="submit">Save placements for this page</button></p></form>{$pager}
</section>
</main>
<script src="/assets/artwork-pagination.js?v=20260622" defer></script>
HTML;
        return Response::html(AdminLayout::render('Artwork Placement', $body));
    }

    public function update(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser, $tenant)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        $visibleIds = array_values(array_unique(array_filter(array_map('intval', is_array($_POST['visible_artwork_ids'] ?? null) ? $_POST['visible_artwork_ids'] : []), static fn (int $id): bool => $id > 0)));
        $validArtwork = array_fill_keys($this->validArtworkIds($tenant, $visibleIds), true);
        $validSections = array_fill_keys(array_map('intval', array_column($this->sections($tenant), 'id')), true);

        $this->pdo->beginTransaction();
        try {
            if ($validArtwork) {
                $placeholders = implode(',', array_fill(0, count($validArtwork), '?'));
                $ids = array_keys($validArtwork);
                $deleteSections = $this->pdo->prepare("DELETE asa FROM artwork_section_assignments asa JOIN artworks a ON a.id = asa.artwork_id WHERE a.tenant_id = ? AND a.id IN ({$placeholders})");
                $deleteSections->execute(array_merge([$tenant->tenantId], $ids));
                $deleteHome = $this->pdo->prepare("DELETE FROM homepage_artwork_assignments WHERE tenant_id = ? AND artwork_id IN ({$placeholders})");
                $deleteHome->execute(array_merge([$tenant->tenantId], $ids));

                $insertSection = $this->pdo->prepare('INSERT INTO artwork_section_assignments (artwork_id, section_id, sort_order, created_at) VALUES (:artwork_id, :section_id, 0, CURRENT_TIMESTAMP)');
                foreach ((array) ($_POST['sections'] ?? []) as $artworkId => $sectionIds) {
                    $artworkId = (int) $artworkId;
                    if (!isset($validArtwork[$artworkId]) || !is_array($sectionIds)) {
                        continue;
                    }
                    foreach (array_unique(array_map('intval', $sectionIds)) as $sectionId) {
                        if (isset($validSections[$sectionId])) {
                            $insertSection->execute(['artwork_id' => $artworkId, 'section_id' => $sectionId]);
                        }
                    }
                }

                $insertHome = $this->pdo->prepare('INSERT INTO homepage_artwork_assignments (tenant_id, artwork_id, sort_order, created_at, updated_at) VALUES (:tenant_id, :artwork_id, :sort_order, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
                $sort = 0;
                foreach (array_unique(array_map('intval', (array) ($_POST['home_artwork_ids'] ?? []))) as $artworkId) {
                    if (isset($validArtwork[$artworkId])) {
                        $insertHome->execute(['tenant_id' => $tenant->tenantId, 'artwork_id' => $artworkId, 'sort_order' => $sort]);
                        $sort += 10;
                    }
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $returnTo = (string) ($_POST['return_to'] ?? '/admin/artworks/placement');
        if (!str_starts_with($returnTo, '/admin/artworks/placement')) {
            $returnTo = '/admin/artworks/placement';
        }
        $separator = str_contains($returnTo, '?') ? '&' : '?';
        return new Response('', 303, ['Location' => $returnTo . $separator . 'notice=saved']);
    }

    public function order(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser, $tenant)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        $sections = array_merge([['id' => 0, 'name' => 'Home page', 'slug' => 'home']], $this->sections($tenant));
        $blocks = '';
        foreach ($sections as $section) {
            $sectionId = (int) $section['id'];
            $items = $sectionId === 0 ? $this->homeOrderItems($tenant) : $this->sectionOrderItems($tenant, $sectionId);
            $rows = '';

            foreach ($items as $i => $item) {
                $artworkId = (int) $item['id'];
                $title = $this->e((string) $item['title']);
                $sort = (int) ($item['sort_order'] ?? ($i * 10));
                $thumb = $this->thumbnailHtml($item, $title, 72, 54);
                $rows .= '<tr draggable="true"><td class="drag-handle" aria-label="Drag handle">↕</td><td>' . $thumb . '</td><td><strong>' . $title . '</strong><br><small>ID ' . $artworkId . '</small></td><td><input class="sort-input" type="number" name="sort[' . $sectionId . '][' . $artworkId . ']" value="' . $sort . '"></td></tr>';
            }

            if ($rows === '') {
                $rows = '<tr><td colspan="4">No artwork assigned. Add artwork from <a href="/admin/artworks/placement">Artwork Placement Matrix</a>.</td></tr>';
            }

            $sectionName = $this->e((string) $section['name']);
            $blocks .= <<<HTML
<section style="margin:2rem 0;">
    <h2>{$sectionName}</h2>
    <table class="admin-table sortable-artworks" border="1" cellpadding="8" cellspacing="0" style="width:100%;border-collapse:collapse;">
        <thead><tr><th>Move</th><th>Thumbnail</th><th>Artwork</th><th>Order</th></tr></thead>
        <tbody>{$rows}</tbody>
    </table>
</section>
HTML;
        }

        $notice = ($_GET['notice'] ?? '') === 'saved'
            ? '<p class="notice" style="padding:.75rem;background:#eef8ee;border:1px solid #9ac99a;">Artwork order saved.</p>'
            : '';

        $body = <<<HTML
<main class="admin-main" style="max-width:1000px;margin:2rem auto;padding:0 1rem;">
    <p><a href="/admin/portfolio-sections">&larr; Portfolio sections</a> · <a href="/admin/artworks/placement">Artwork placement matrix</a></p>
    <h1>Portfolio Section Artwork Order</h1>
    <p>Drag rows or edit numeric order fields. Home page is included as a section. Lower numbers appear first.</p>
    {$notice}
    <form method="post" action="/admin/portfolio-sections/order">
        {$blocks}
        <p><button type="submit">Save order</button></p>
    </form>
</main>
<script src="/assets/admin/artwork-placement-order.js"></script>
HTML;

        return Response::html(AdminLayout::render('Artwork Order', $body));
    }

    public function updateOrder(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser, $tenant)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        $sort = is_array($_POST['sort'] ?? null) ? $_POST['sort'] : [];
        $this->pdo->beginTransaction();
        try {
            $updateSection = $this->pdo->prepare(
                'UPDATE artwork_section_assignments asa
                 JOIN artworks a ON a.id = asa.artwork_id
                 JOIN portfolio_sections ps ON ps.id = asa.section_id
                 SET asa.sort_order = :sort_order
                 WHERE asa.artwork_id = :artwork_id
                   AND asa.section_id = :section_id
                   AND a.tenant_id = :tenant_id
                   AND ps.tenant_id = :tenant_id'
            );
            $updateHome = $this->pdo->prepare(
                'UPDATE homepage_artwork_assignments
                 SET sort_order = :sort_order, updated_at = CURRENT_TIMESTAMP
                 WHERE tenant_id = :tenant_id AND artwork_id = :artwork_id'
            );

            foreach ($sort as $sectionId => $artworkSorts) {
                $sectionId = (int) $sectionId;
                if (!is_array($artworkSorts)) {
                    continue;
                }

                foreach ($artworkSorts as $artworkId => $sortOrder) {
                    $params = [
                        'tenant_id' => $tenant->tenantId,
                        'artwork_id' => (int) $artworkId,
                        'sort_order' => (int) $sortOrder,
                    ];

                    if ($sectionId === 0) {
                        $updateHome->execute($params);
                    } else {
                        $params['section_id'] = $sectionId;
                        $updateSection->execute($params);
                    }
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return new Response('', 303, ['Location' => '/admin/portfolio-sections/order?notice=saved']);
    }

    private function canManage(?array $currentUser, TenantContext $tenant): bool
    {
        return $this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin']);
    }

    private function sections(TenantContext $tenant): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, slug
             FROM portfolio_sections
             WHERE tenant_id = :tenant_id AND status <> 'archived'
             ORDER BY sort_order ASC, name ASC"
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId]);

        return $stmt->fetchAll();
    }

    private function artworksPage(TenantContext $tenant, string $q, int $page, int $pageSize): array
    {
        $where = "a.tenant_id = :tenant_id AND a.status <> 'archived'";
        $params = ['tenant_id' => $tenant->tenantId];
        if ($q !== '') {
            $where .= ' AND (a.title LIKE :q OR a.medium LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM artworks a WHERE {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pageCount = max(1, (int) ceil($total / $pageSize));
        $page = min(max(1, $page), $pageCount);
        $stmt = $this->pdo->prepare("SELECT a.id, a.title, a.status, m.uuid AS media_uuid FROM artworks a LEFT JOIN media_assets m ON m.id = a.primary_media_id WHERE {$where} ORDER BY a.title ASC, a.id ASC LIMIT :limit_count OFFSET :offset_count");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue('limit_count', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue('offset_count', ($page - 1) * $pageSize, PDO::PARAM_INT);
        $stmt->execute();
        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'page_count' => $pageCount];
    }

    private function validArtworkIds(TenantContext $tenant, array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT id FROM artworks WHERE tenant_id = ? AND status <> 'archived' AND id IN ({$placeholders})");
        $stmt->execute(array_merge([$tenant->tenantId], $ids));
        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }

    private function sectionAssignmentsForArtworks(TenantContext $tenant, array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT asa.artwork_id, asa.section_id FROM artwork_section_assignments asa JOIN artworks a ON a.id = asa.artwork_id JOIN portfolio_sections ps ON ps.id = asa.section_id WHERE a.tenant_id = ? AND ps.tenant_id = ? AND a.id IN ({$placeholders})");
        $stmt->execute(array_merge([$tenant->tenantId, $tenant->tenantId], $ids));
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['artwork_id']][(int) $row['section_id']] = true;
        }
        return $map;
    }

    private function homeAssignmentsForArtworks(TenantContext $tenant, array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT artwork_id FROM homepage_artwork_assignments WHERE tenant_id = ? AND artwork_id IN ({$placeholders})");
        $stmt->execute(array_merge([$tenant->tenantId], $ids));
        return array_fill_keys(array_map('intval', array_column($stmt->fetchAll(), 'artwork_id')), true);
    }

    private function pager(string $path, array $query, int $page, int $pageCount): string
    {
        if ($pageCount <= 1) {
            return '';
        }
        $html = '<nav aria-label="Pages" style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;margin:1rem 0;">';
        $html .= $this->pageStepLink($path, $query, $page - 1, '‹ Previous', $page <= 1);
        for ($n = 1; $n <= $pageCount; $n++) {
            $href = $path . '?' . http_build_query(array_merge($query, ['page' => $n]));
            $current = $n === $page ? ' aria-current="page" style="font-weight:bold;text-decoration:underline;"' : '';
            $html .= '<a data-artwork-page-link href="' . $this->e($href) . '"' . $current . '>' . $n . '</a>';
        }
        $html .= $this->pageStepLink($path, $query, $page + 1, 'Next ›', $page >= $pageCount);
        return $html . '</nav>';
    }

    private function pageStepLink(string $path, array $query, int $page, string $label, bool $disabled): string
    {
        if ($disabled) {
            return '<span aria-disabled="true" style="opacity:.45;padding:.25rem .45rem;border:1px solid #bbb;">' . $this->e($label) . '</span>';
        }

        $href = $path . '?' . http_build_query(array_merge($query, ['page' => max(1, $page)]));
        return '<a data-artwork-page-link class="page-step" href="' . $this->e($href) . '" style="padding:.25rem .45rem;border:1px solid currentColor;text-decoration:none;">' . $this->e($label) . '</a>';
    }


    private function homeOrderItems(TenantContext $tenant): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.id, a.title, h.sort_order, m.uuid AS media_uuid
             FROM homepage_artwork_assignments h
             JOIN artworks a ON a.id = h.artwork_id
             LEFT JOIN media_assets m ON m.id = a.primary_media_id
             WHERE h.tenant_id = :tenant_id AND a.tenant_id = :tenant_id
             ORDER BY h.sort_order ASC, a.title ASC'
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId]);

        return $stmt->fetchAll();
    }

    private function sectionOrderItems(TenantContext $tenant, int $sectionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.id, a.title, asa.sort_order, m.uuid AS media_uuid
             FROM artwork_section_assignments asa
             JOIN artworks a ON a.id = asa.artwork_id
             JOIN portfolio_sections ps ON ps.id = asa.section_id
             LEFT JOIN media_assets m ON m.id = a.primary_media_id
             WHERE a.tenant_id = :tenant_id
               AND ps.tenant_id = :tenant_id
               AND asa.section_id = :section_id
             ORDER BY asa.sort_order ASC, a.title ASC'
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'section_id' => $sectionId]);

        return $stmt->fetchAll();
    }

    private function thumbnailHtml(array $artwork, string $title, int $width, int $height): string
    {
        if (empty($artwork['media_uuid'])) {
            return '<span style="display:inline-block;width:' . $width . 'px;height:' . $height . 'px;border:1px dashed #bbb;background:#f6f6f6;text-align:center;line-height:' . $height . 'px;color:#777;">No image</span>';
        }

        $src = '/admin/media?uuid=' . rawurlencode((string) $artwork['media_uuid']);

        return '<img src="' . $this->e($src) . '" alt="' . $title . '" style="width:' . $width . 'px;height:' . $height . 'px;object-fit:contain;background:#fff;border:1px solid #ddd;">';
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
