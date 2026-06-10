<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Http\View\ErrorPage;
use App\Platform\Tenancy\TenantContext;
use PDO;

/**
 * Provides matrix-style artwork placement management for sections and home.
 */
final class ArtworkPlacementController
{
    public function __construct(private readonly RequireTenantRoleBrowser $roles, private readonly PDO $pdo) {}

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser, $tenant)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }
        $sections = $this->sections($tenant);
        $artworks = $this->artworks($tenant);
        $assignments = $this->sectionAssignments($tenant);
        $homeAssignments = $this->homeAssignments($tenant);
        $sectionHeaders = '';
        foreach ($sections as $section) {
            $sectionHeaders .= '<th>' . $this->e((string) $section['name']) . '</th>';
        }
        $rows = '';
        foreach ($artworks as $artwork) {
            $id = (int) $artwork['id'];
            $title = $this->e((string) $artwork['title']);
            $thumb = '';
            if (!empty($artwork['media_uuid'])) {
                $src = '/admin/media?uuid=' . rawurlencode((string) $artwork['media_uuid']);
                $thumb = '<img src="' . $this->e($src) . '" alt="' . $title . '" style="width:96px;height:72px;object-fit:contain;background:#fff;border:1px solid #ddd;">';
            }
            $homeChecked = isset($homeAssignments[$id]) ? ' checked' : '';
            $cells = '<td style="text-align:center"><input type="checkbox" name="home_artwork_ids[]" value="' . $id . '"' . $homeChecked . '></td>';
            foreach ($sections as $section) {
                $sectionId = (int) $section['id'];
                $checked = isset($assignments[$id][$sectionId]) ? ' checked' : '';
                $cells .= '<td style="text-align:center"><input type="checkbox" name="sections[' . $id . '][]" value="' . $sectionId . '"' . $checked . '></td>';
            }
            $rows .= '<tr><td>' . $thumb . '</td><td><strong>' . $title . '</strong><br><small>ID ' . $id . '</small></td>' . $cells . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="' . (3 + count($sections)) . '">No artworks found.</td></tr>';
        }
        $notice = ($_GET['notice'] ?? '') === 'saved' ? '<p style="padding:.75rem;background:#eef8ee;border:1px solid #9ac99a;">Artwork placements saved.</p>' : '';
        $body = <<<HTML
<main class="admin-main" style="max-width:1200px;margin:2rem auto;padding:0 1rem;">
    <p><a href="/admin/artworks">&larr; Artworks</a> · <a href="/admin/portfolio-sections/order">Section artwork order</a></p>
    <h1>Artwork Placement Matrix</h1>
    <p>Use this alternate artworks page to place artwork on the home page and in portfolio sections.</p>
    {$notice}
    <form method="post" action="/admin/artworks/placement">
        <table border="1" cellpadding="8" cellspacing="0" style="width:100%;border-collapse:collapse;">
            <thead><tr><th>Thumbnail</th><th>Artwork</th><th>Home page</th>{$sectionHeaders}</tr></thead>
            <tbody>{$rows}</tbody>
        </table>
        <p><button type="submit">Save placements</button></p>
    </form>
</main>
HTML;
        return Response::html(AdminLayout::render('Artwork Placement', $body));
    }

    public function update(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser, $tenant)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }
        $artworkIds = array_map('intval', array_column($this->artworks($tenant), 'id'));
        $validArtwork = array_fill_keys($artworkIds, true);
        $validSections = array_fill_keys(array_map('intval', array_column($this->sections($tenant), 'id')), true);
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("DELETE asa FROM artwork_section_assignments asa JOIN artworks a ON a.id = asa.artwork_id WHERE a.tenant_id = :tenant_id")->execute(['tenant_id' => $tenant->tenantId]);
            $insertSection = $this->pdo->prepare("INSERT INTO artwork_section_assignments (artwork_id, section_id, sort_order, created_at) VALUES (:artwork_id, :section_id, 0, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order)");
            $postedSections = is_array($_POST['sections'] ?? null) ? $_POST['sections'] : [];
            foreach ($postedSections as $artworkId => $sectionIds) {
                $artworkId = (int) $artworkId;
                if (!isset($validArtwork[$artworkId]) || !is_array($sectionIds)) { continue; }
                foreach (array_unique(array_map('intval', $sectionIds)) as $sectionId) {
                    if (isset($validSections[$sectionId])) { $insertSection->execute(['artwork_id' => $artworkId, 'section_id' => $sectionId]); }
                }
            }
            $this->pdo->prepare('DELETE FROM homepage_artwork_assignments WHERE tenant_id = :tenant_id')->execute(['tenant_id' => $tenant->tenantId]);
            $insertHome = $this->pdo->prepare("INSERT INTO homepage_artwork_assignments (tenant_id, artwork_id, sort_order, created_at) VALUES (:tenant_id, :artwork_id, :sort_order, CURRENT_TIMESTAMP)");
            $homeIds = is_array($_POST['home_artwork_ids'] ?? null) ? $_POST['home_artwork_ids'] : [];
            $order = 0;
            foreach (array_unique(array_map('intval', $homeIds)) as $artworkId) {
                if (isset($validArtwork[$artworkId])) { $insertHome->execute(['tenant_id' => $tenant->tenantId, 'artwork_id' => $artworkId, 'sort_order' => $order]); $order += 10; }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
        return new Response('', 303, ['Location' => '/admin/artworks/placement?notice=saved']);
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
                $thumb = !empty($item['media_uuid']) ? '<img src="/admin/media?uuid=' . rawurlencode((string) $item['media_uuid']) . '" alt="' . $title . '" style="width:72px;height:54px;object-fit:contain;background:#fff;border:1px solid #ddd;">' : '';
                $rows .= '<tr draggable="true"><td class="drag-handle">↕</td><td>' . $thumb . '</td><td>' . $title . '</td><td><input class="sort-input" type="number" name="sort[' . $sectionId . '][' . $artworkId . ']" value="' . $sort . '"></td></tr>';
            }
            if ($rows === '') { $rows = '<tr><td colspan="4">No artwork assigned.</td></tr>'; }
            $blocks .= '<section style="margin:2rem 0"><h2>' . $this->e((string) $section['name']) . '</h2><table class="sortable-artworks" border="1" cellpadding="8" cellspacing="0" style="width:100%;border-collapse:collapse"><tbody>' . $rows . '</tbody></table></section>';
        }
        $notice = ($_GET['notice'] ?? '') === 'saved' ? '<p style="padding:.75rem;background:#eef8ee;border:1px solid #9ac99a;">Artwork order saved.</p>' : '';
        $body = <<<HTML
<main class="admin-main" style="max-width:1000px;margin:2rem auto;padding:0 1rem;">
<p><a href="/admin/portfolio-sections">&larr; Portfolio sections</a> · <a href="/admin/artworks/placement">Artwork placement matrix</a></p>
<h1>Portfolio Section Artwork Order</h1><p>Drag rows or edit numeric order fields. Lower numbers appear first.</p>{$notice}
<form method="post" action="/admin/portfolio-sections/order">{$blocks}<p><button type="submit">Save order</button></p></form></main>
<script>document.querySelectorAll('.sortable-artworks tbody').forEach(function(tbody){let dragged=null;tbody.addEventListener('dragstart',function(e){dragged=e.target.closest('tr')});tbody.addEventListener('dragover',function(e){e.preventDefault();const row=e.target.closest('tr');if(!dragged||!row||row===dragged)return;const box=row.getBoundingClientRect();tbody.insertBefore(dragged,e.clientY<box.top+box.height/2?row:row.nextSibling);Array.from(tbody.querySelectorAll('.sort-input')).forEach(function(input,index){input.value=index*10})})});</script>
HTML;
        return Response::html(AdminLayout::render('Artwork Order', $body));
    }

    public function updateOrder(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser, $tenant)) { return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403); }
        $sort = is_array($_POST['sort'] ?? null) ? $_POST['sort'] : [];
        $this->pdo->beginTransaction();
        try {
            $updateSection = $this->pdo->prepare("UPDATE artwork_section_assignments asa JOIN artworks a ON a.id = asa.artwork_id JOIN portfolio_sections ps ON ps.id = asa.section_id SET asa.sort_order = :sort_order WHERE asa.artwork_id = :artwork_id AND asa.section_id = :section_id AND a.tenant_id = :tenant_id AND ps.tenant_id = :tenant_id");
            $updateHome = $this->pdo->prepare("UPDATE homepage_artwork_assignments SET sort_order = :sort_order, updated_at = CURRENT_TIMESTAMP WHERE tenant_id = :tenant_id AND artwork_id = :artwork_id");
            foreach ($sort as $sectionId => $artworkSorts) {
                $sectionId = (int) $sectionId;
                if (!is_array($artworkSorts)) { continue; }
                foreach ($artworkSorts as $artworkId => $sortOrder) {
                    $params = ['tenant_id' => $tenant->tenantId, 'artwork_id' => (int) $artworkId, 'sort_order' => (int) $sortOrder];
                    if ($sectionId === 0) { $updateHome->execute($params); } else { $params['section_id'] = $sectionId; $updateSection->execute($params); }
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
        return new Response('', 303, ['Location' => '/admin/portfolio-sections/order?notice=saved']);
    }

    private function canManage(?array $currentUser, TenantContext $tenant): bool { return $this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin']); }
    private function sections(TenantContext $tenant): array { $stmt=$this->pdo->prepare("SELECT id,name,slug FROM portfolio_sections WHERE tenant_id=:tenant_id AND status <> 'archived' ORDER BY sort_order ASC,name ASC"); $stmt->execute(['tenant_id'=>$tenant->tenantId]); return $stmt->fetchAll(); }
    private function artworks(TenantContext $tenant): array { $stmt=$this->pdo->prepare("SELECT a.id,a.title,a.status,m.uuid AS media_uuid FROM artworks a LEFT JOIN media_assets m ON m.id=a.primary_media_id WHERE a.tenant_id=:tenant_id AND a.status <> 'archived' ORDER BY a.title ASC,a.id ASC"); $stmt->execute(['tenant_id'=>$tenant->tenantId]); return $stmt->fetchAll(); }
    private function sectionAssignments(TenantContext $tenant): array { $stmt=$this->pdo->prepare("SELECT asa.artwork_id,asa.section_id FROM artwork_section_assignments asa JOIN artworks a ON a.id=asa.artwork_id JOIN portfolio_sections ps ON ps.id=asa.section_id WHERE a.tenant_id=:tenant_id AND ps.tenant_id=:tenant_id"); $stmt->execute(['tenant_id'=>$tenant->tenantId]); $map=[]; foreach($stmt->fetchAll() as $r){$map[(int)$r['artwork_id']][(int)$r['section_id']]=true;} return $map; }
    private function homeAssignments(TenantContext $tenant): array { $stmt=$this->pdo->prepare('SELECT artwork_id FROM homepage_artwork_assignments WHERE tenant_id=:tenant_id'); $stmt->execute(['tenant_id'=>$tenant->tenantId]); return array_fill_keys(array_map('intval', array_column($stmt->fetchAll(), 'artwork_id')), true); }
    private function homeOrderItems(TenantContext $tenant): array { $stmt=$this->pdo->prepare("SELECT a.id,a.title,h.sort_order,m.uuid AS media_uuid FROM homepage_artwork_assignments h JOIN artworks a ON a.id=h.artwork_id LEFT JOIN media_assets m ON m.id=a.primary_media_id WHERE h.tenant_id=:tenant_id AND a.tenant_id=:tenant_id ORDER BY h.sort_order ASC,a.title ASC"); $stmt->execute(['tenant_id'=>$tenant->tenantId]); return $stmt->fetchAll(); }
    private function sectionOrderItems(TenantContext $tenant, int $sectionId): array { $stmt=$this->pdo->prepare("SELECT a.id,a.title,asa.sort_order,m.uuid AS media_uuid FROM artwork_section_assignments asa JOIN artworks a ON a.id=asa.artwork_id LEFT JOIN media_assets m ON m.id=a.primary_media_id WHERE a.tenant_id=:tenant_id AND asa.section_id=:section_id ORDER BY asa.sort_order ASC,a.title ASC"); $stmt->execute(['tenant_id'=>$tenant->tenantId,'section_id'=>$sectionId]); return $stmt->fetchAll(); }
    private function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
}

// End of file.
