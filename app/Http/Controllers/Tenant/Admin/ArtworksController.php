<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;


use App\Http\View\ErrorPage;
use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Directory\TenantDirectoryProfileRepository;
use App\Platform\Tenancy\TenantContext;
use App\Platform\Audit\AuditLogRepository;
use App\Support\Pagination\Pagination;
use App\Tenant\Sales\ArtworkSaleAdminForm;
use PDO;
use App\Http\View\AdminLayout;
use Throwable;

final class ArtworksController
{
    /* artwork save return_to is normalized by safeArtworkReturnTo(). */
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly PDO $pdo,
        private readonly AuditLogRepository $auditLog,
    ) {
        $this->rememberArtworkGridReturnUrl();

    }

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        $this->rememberArtworkGridReturnUrl();


        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        $q = trim((string) ($_GET['q'] ?? ''));
        $sort = (string) ($_GET['sort'] ?? 'created_desc');
        $statusFilter = (string) ($_GET['status'] ?? '');
        $saleFilter = (string) ($_GET['sale_status'] ?? '');
        $imageFilter = (string) ($_GET['image'] ?? '');
        $sectionFilter = max(0, (int) ($_GET['section_id'] ?? 0));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $pageSize = Pagination::allowedLimitFromQuery(
            $_GET['per_page'] ?? null,
            50,
            Pagination::standardPageSizes(),
        );
        $offset = ($page - 1) * $pageSize;

        $orderBy = match ($sort) {
            'name' => 'a.title ASC, a.id ASC',
            'medium' => 'a.medium ASC, a.title ASC, a.id ASC',
            'date' => 'a.year_created DESC, a.title ASC, a.id ASC',
            'status' => 'a.status ASC, a.title ASC, a.id ASC',
            default => 'a.id DESC',
        };

        $where = "a.tenant_id = :tenant_id AND a.status <> 'archived'";
        $params = ['tenant_id' => $tenant->tenantId];
        if ($q !== '') {
            $where .= ' AND (a.title LIKE :q OR a.medium LIKE :q OR a.description LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        if (in_array($statusFilter, ['draft', 'published'], true)) {
            $where .= ' AND a.status = :status_filter';
            $params['status_filter'] = $statusFilter;
        }
        if (in_array($saleFilter, ['nfs', 'for_sale', 'sold'], true)) {
            $where .= ' AND a.sale_status = :sale_filter';
            $params['sale_filter'] = $saleFilter;
        }
        if ($imageFilter === 'missing') {
            $where .= ' AND a.primary_media_id IS NULL';
        } elseif ($imageFilter === 'present') {
            $where .= ' AND a.primary_media_id IS NOT NULL';
        }
        if ($sectionFilter > 0) {
            $where .= ' AND EXISTS (SELECT 1 FROM artwork_section_assignments af WHERE af.artwork_id = a.id AND af.section_id = :section_filter)';
            $params['section_filter'] = $sectionFilter;
        }

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM artworks a WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pageCount = max(1, (int) ceil($total / $pageSize));
        if ($page > $pageCount) {
            $page = $pageCount;
            $offset = ($page - 1) * $pageSize;
        }

        $stmt = $this->pdo->prepare(
            "SELECT a.id, a.title, a.slug, a.description, a.medium, a.year_created, a.status,
                    a.sale_status, a.price, COALESCE(a.is_one_off, 1) AS is_one_off,
                    COALESCE(a.inventory_quantity, 1) AS inventory_quantity,
                    (SELECT ts.setting_value FROM tenant_settings ts
                     WHERE ts.tenant_id = a.tenant_id AND ts.setting_key = 'platform_directory_thumbnail_artwork_id'
                     LIMIT 1) AS directory_thumbnail_artwork_id,
                    a.created_at,
                    (SELECT GROUP_CONCAT(atype.code ORDER BY atype.code SEPARATOR ',')
                     FROM artwork_type_assignments ata
                     JOIN artwork_types atype ON atype.id = ata.type_id
                     WHERE ata.artwork_id = a.id) AS artwork_type_codes,
                    m.id AS media_id, m.uuid AS media_uuid, m.storage_path, m.mime_type, m.width, m.height
             FROM artworks a
             LEFT JOIN media_assets m ON m.id = a.primary_media_id
             WHERE {$where}
             ORDER BY {$orderBy}
             LIMIT :limit_count OFFSET :offset_count"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue('limit_count', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue('offset_count', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();


        // ARTSFOLIO_PORTFOLIO_SECTIONS_ALPHA_MARKER: artwork edit portfolio sections are ordered alphabetically for predictable editing.
        $sectionsStmt = $this->pdo->prepare("SELECT id, name FROM portfolio_sections WHERE tenant_id = :tenant_id AND status <> 'archived' ORDER BY LOWER(name), name, id");
        $sectionsStmt->execute(['tenant_id' => $tenant->tenantId]);
        $sections = $sectionsStmt->fetchAll();

        $baseQuery = array_filter([
            'q' => $q,
            'sort' => $sort,
            'status' => $statusFilter,
            'sale_status' => $saleFilter,
            'image' => $imageFilter,
            'section_id' => $sectionFilter > 0 ? $sectionFilter : '',
            'per_page' => $pageSize,
        ], static fn ($value): bool => $value !== '');
        $returnTo = '/admin/artworks?' . http_build_query(array_merge($baseQuery, ['page' => $page]));
        $returnToValue = htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8');
        $items = '';

        foreach ($rows as $row) {
            $title = htmlspecialchars((string) $row['title'], ENT_QUOTES, 'UTF-8');
            $status = htmlspecialchars((string) $row['status'], ENT_QUOTES, 'UTF-8');
            $typeBadges = $this->artworkTypeBadges((string) ($row['artwork_type_codes'] ?? ''));
            $saleStatus = htmlspecialchars((string) $row['sale_status'], ENT_QUOTES, 'UTF-8');
            $price = htmlspecialchars((string) ($row['price'] ?? ''), ENT_QUOTES, 'UTF-8');
            $medium = htmlspecialchars((string) ($row['medium'] ?? ''), ENT_QUOTES, 'UTF-8');
            $year = htmlspecialchars((string) ($row['year_created'] ?? ''), ENT_QUOTES, 'UTF-8');
            $notes = htmlspecialchars((string) ($row['description'] ?? ''), ENT_QUOTES, 'UTF-8');
            $created = htmlspecialchars((string) $row['created_at'], ENT_QUOTES, 'UTF-8');
            $image = '';
            $artworkId = (int) $row['id'];
            $isDirectoryThumbnail = (int) ($row['directory_thumbnail_artwork_id'] ?? 0) === $artworkId;
            $directoryChecked = $isDirectoryThumbnail ? ' checked' : '';
            $directoryDisabled = '';
            $directoryHelp = $isDirectoryThumbnail ? 'Current directory thumbnail' : 'Use as directory thumbnail';
            if ((string) $row['status'] !== 'published') {
                $directoryDisabled = ' disabled';
                $directoryHelp = 'Publish first';
            } elseif (empty($row['storage_path'])) {
                $directoryDisabled = ' disabled';
                $directoryHelp = 'Primary image required';
            }
            $directoryHelp = htmlspecialchars($directoryHelp, ENT_QUOTES, 'UTF-8');
            if (!empty($row['storage_path'])) {
                $src = htmlspecialchars('/admin/media?uuid=' . rawurlencode((string) $row['media_uuid']) . '&variant=thumb', ENT_QUOTES, 'UTF-8');
                $image = "<img src=\"{$src}\" alt=\"{$title}\" style=\"max-width:180px;max-height:140px;object-fit:contain;border:1px solid #ddd;background:#fff;\">";
            }
            $items .= <<<HTML
<tr id="artwork-{$row['id']}">
    <td><a class="artwork-grid-thumbnail-link" href="/admin/artworks/edit?id={$artworkId}&return_to={$returnToValue}#artwork-{$artworkId}">{$image}</a></td><td><strong>{$title}</strong><br><small>ID {$row['id']} · {$created}</small></td>
    <td>{$year}</td><td>{$medium}</td><td class="js-artwork-status">{$status}<br>{$typeBadges}</td>
    <td>{$saleStatus}</td><td>{$price}</td><td>{$notes}</td>
    <td><form method="post" action="/admin/artworks/directory-thumbnail"><input type="hidden" name="id" value="{$artworkId}"><input type="hidden" name="return_to" value="{$returnToValue}"><label><input type="checkbox" name="directory_thumbnail" value="1"{$directoryChecked}{$directoryDisabled} onchange="this.form.submit()"> Directory thumbnail</label><br><small>{$directoryHelp}</small></form></td>
    <td><a class="admin-button" href="/admin/artworks/edit?id={$row['id']}">Edit</a> {$this->statusActionButton($row, $returnToValue)} <form method="post" action="/admin/artworks/delete" class="js-artwork-action" style="display:inline" onsubmit="return confirm('Archive this artwork?');"><input type="hidden" name="id" value="{$row['id']}"><input type="hidden" name="return_to" value="{$returnToValue}"><button type="submit">Archive</button></form></td>
</tr>
HTML;
        }
        if ($items === '') {
            $items = '<tr><td colspan="10">No artwork matches these filters.</td></tr>';
        }

        $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $option = static fn (string $current, string $value): string => $current === $value ? ' selected' : '';
        $pageSizeOptions = '';
        foreach (Pagination::standardPageSizes() as $sizeOption) {
            $selected = $sizeOption === $pageSize ? ' selected' : '';
            $label = $sizeOption === 50 ? '50 (default)' : (string) $sizeOption;
            $pageSizeOptions .= '<option value="' . $sizeOption . '"' . $selected . '>' . $label . '</option>';
        }

        $sectionOptions = '<option value="">All sections</option>';
        foreach ($sections as $section) {
            $sid = (int) $section['id'];
            $selected = $sectionFilter === $sid ? ' selected' : '';
            $sectionOptions .= '<option value="' . $sid . '"' . $selected . '>' . $e((string) $section['name']) . '</option>';
        }

        $pager = '';
        if ($pageCount > 1) {
            $pager = '<nav aria-label="Artwork pages" style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;margin:1rem 0;">';
            $pager .= $this->pageStepLink('/admin/artworks', $baseQuery, $page - 1, '‹ Previous', $page <= 1);
            for ($n = 1; $n <= $pageCount; $n++) {
                $href = '/admin/artworks?' . http_build_query(array_merge($baseQuery, ['page' => $n]));
                $current = $n === $page ? ' aria-current="page" style="font-weight:bold;text-decoration:underline;"' : '';
                $pager .= '<a data-artwork-page-link href="' . $e($href) . '"' . $current . '>' . $n . '</a>';
            }
            $pager .= $this->pageStepLink('/admin/artworks', $baseQuery, $page + 1, 'Next ›', $page >= $pageCount);
            $pager .= '</nav>';
        }

        $notice = match ((string) ($_GET['notice'] ?? '')) {
            'status-updated' => '<p class="notice">Artwork status updated.</p>',
            'artwork-archived' => '<p class="notice">Artwork archived.</p>',
            'artwork-saved' => '<p class="notice">Artwork saved.</p>',
            'directory-thumbnail-updated' => '<p class="notice">Directory thumbnail updated.</p>',
            default => '',
        };

        $summary = $total === 0 ? 'No artworks' : 'Showing ' . ($offset + 1) . '–' . min($offset + $pageSize, $total) . ' of ' . $total;
        $body = <<<HTML
<main>
<div id="artwork-action-notice">{$notice}</div>
<section data-artwork-pager-root tabindex="-1">
<section aria-label="Artwork actions" class="tenant-admin-action-grid">
    <a class="admin-button tenant-admin-action-button" href="/admin/artwork/upload">
        <strong>Upload artwork</strong>
        <span>Add a new image, catalog details, publication state, and sales information.</span>
    </a>
    <a class="admin-button tenant-admin-action-button" href="/admin/artworks/placement">
        <strong>Artwork placement matrix</strong>
        <span>Assign many artworks to the home page and portfolio sections at once.</span>
    </a>
    <a class="admin-button tenant-admin-action-button" href="/admin/portfolio-sections/order">
        <strong>Section artwork order</strong>
        <span>Set the display order within each portfolio section and on the home page.</span>
    </a>
</section>
<form data-artwork-page-form method="get" action="/admin/artworks" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:end;margin:1rem 0;">
<label>Search<br><input type="search" name="q" value="{$e($q)}"></label>
<label>Status<br><select name="status"><option value="">All</option><option value="draft"{$option($statusFilter,'draft')}>Draft</option><option value="published"{$option($statusFilter,'published')}>Published</option></select></label>
<label>Sale<br><select name="sale_status"><option value="">All</option><option value="nfs"{$option($saleFilter,'nfs')}>Not for sale</option><option value="for_sale"{$option($saleFilter,'for_sale')}>For sale</option><option value="sold"{$option($saleFilter,'sold')}>Sold</option></select></label>
<label>Image<br><select name="image"><option value="">All</option><option value="present"{$option($imageFilter,'present')}>Has image</option><option value="missing"{$option($imageFilter,'missing')}>Missing image</option></select></label>
<label>Section<br><select name="section_id">{$sectionOptions}</select></label>
<label>Sort<br><select name="sort"><option value="created_desc"{$option($sort,'created_desc')}>Newest</option><option value="name"{$option($sort,'name')}>Name</option><option value="medium"{$option($sort,'medium')}>Medium</option><option value="date"{$option($sort,'date')}>Date/year</option><option value="status"{$option($sort,'status')}>Status</option></select></label>
<label>Artworks per page<br><select name="per_page">{$pageSizeOptions}</select></label>
<button type="submit">Apply</button><a href="/admin/artworks">Clear</a>
</form>
<p><strong>{$summary}</strong></p>{$pager}
<div style="overflow-x:auto;"><table border="1" cellpadding="8" cellspacing="0"><thead><tr><th>Image</th><th>Title</th><th>Date/year</th><th>Medium</th><th>Status</th><th>Sale</th><th>Price</th><th>Notes</th><th>Directory thumbnail</th><th>Actions</th></tr></thead><tbody>{$items}</tbody></table></div>
{$pager}
</section>
</main>
<script src="/assets/admin/artworks.js"></script>
<script src="/assets/artwork-pagination.js?v=20260622" defer></script>
HTML;
        return Response::html(AdminLayout::render('Artworks', $body));
    }

    private function pageStepLink(string $path, array $query, int $page, string $label, bool $disabled): string
    {
        $escapedLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        if ($disabled) {
            return '<span aria-disabled="true" style="opacity:.45;padding:.25rem .45rem;border:1px solid #bbb;">' . $escapedLabel . '</span>';
        }

        $href = $path . '?' . http_build_query(array_merge($query, ['page' => max(1, $page)]));
        return '<a data-artwork-page-link class="page-step" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" style="padding:.25rem .45rem;border:1px solid currentColor;text-decoration:none;">' . $escapedLabel . '</a>';
    }

    public function edit(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        $this->rememberArtworkGridReturnUrl();


        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        $id = (int) ($_GET['id'] ?? 0);
        $artwork = $this->findArtwork($tenant, $id);

        if (!$artwork) {
            return Response::html('<h1>404</h1><p>Artwork not found.</p>', 404);
        }

        $title = htmlspecialchars((string) $artwork['title'], ENT_QUOTES, 'UTF-8');
        $year = htmlspecialchars((string) ($artwork['year_created'] ?? ''), ENT_QUOTES, 'UTF-8');
        $medium = htmlspecialchars((string) ($artwork['medium'] ?? ''), ENT_QUOTES, 'UTF-8');
        $notes = htmlspecialchars((string) ($artwork['description'] ?? ''), ENT_QUOTES, 'UTF-8');
        $price = htmlspecialchars((string) ($artwork['price'] ?? ''), ENT_QUOTES, 'UTF-8');
        $status = (string) $artwork['status'];
        $saleStatus = (string) $artwork['sale_status'];
        $sections = $this->portfolioSections($tenant);
        // ARTWORK_EDIT_SECTIONS_ALPHA_MARKER: keep artwork edit portfolio sections alphabetized for scanability.
        if (is_array($sections)) {
            usort($sections, static fn (array $a, array $b): int => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
        }
        $selectedSectionIds = $this->artworkSectionIds($tenant, $id);
        $selectedTypeCodes = $this->artworkTypeCodes($id);
        $artworkPreview = '';
        $primaryMediaUuid = trim((string) ($artwork['primary_media_uuid'] ?? ''));
        if ($primaryMediaUuid !== '') {
            $previewSrc = htmlspecialchars(
                '/admin/media?uuid=' . rawurlencode($primaryMediaUuid) . '&variant=large',
                ENT_QUOTES,
                'UTF-8',
            );
            $artworkPreview = <<<HTML
    <figure class="artwork-edit-preview">
        <img src="{$previewSrc}" alt="{$title}">
        <figcaption>Current primary artwork image</figcaption>
    </figure>
HTML;
        } else {
            $artworkPreview = '<p class="admin-muted">This artwork does not currently have a primary image.</p>';
        }
        $portfolioChecked = in_array('portfolio_images', $selectedTypeCodes, true) ? ' checked' : '';
        $siteChecked = in_array('site_images', $selectedTypeCodes, true) ? ' checked' : '';
        $sectionOptions = '';

        foreach ($sections as $section) {
            $sectionId = (int) $section['id'];
            $sectionName = htmlspecialchars((string) $section['name'], ENT_QUOTES, 'UTF-8');
            $checked = in_array($sectionId, $selectedSectionIds, true) ? ' checked' : '';
            $sectionOptions .= "<label style=\"display:block;margin:.25rem 0;\"><input type=\"checkbox\" name=\"section_ids[]\" value=\"{$sectionId}\"{$checked}> {$sectionName}</label>\n";
        }

        if ($sectionOptions === '') {
            $sectionOptions = '<p>No portfolio sections exist yet. Create them from <a href="/admin/portfolio-sections">Portfolio Sections</a>.</p>';
        }

        $selected = fn (string $a, string $b): string => $a === $b ? ' selected' : '';
        $saleFieldset = '';
        try {
            $saleFieldset = (new ArtworkSaleAdminForm($this->pdo))->render($tenant->tenantId, $artwork);
        $notesValue = htmlspecialchars((string) ($artwork['notes'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $notesHtmlValue = htmlspecialchars((string) ($artwork['notes_html'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $returnTo = (string) ($_GET['return_to'] ?? '/admin/artworks');
        if ($returnTo === '' || $returnTo[0] !== '/' || str_starts_with($returnTo, '//')) {
            $returnTo = '/admin/artworks';
        }
        $returnToValue = htmlspecialchars($returnTo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        } catch (\Throwable $exception) {
            $this->logAdminArtworkEditFailure($tenant->tenantId, (int) ($artwork['id'] ?? 0), 'ArtworkSaleAdminForm render failed', $exception);
            $saleFieldset = '<fieldset class="admin-card admin-warning"><legend>Sales &amp; checkout</legend><p>Sales settings could not be loaded. The rest of the artwork form is still available. Check <code>storage/logs/admin_artwork_edit.log</code>.</p></fieldset>';
        }

        $body = <<<HTML
<main>
    <p><a href="/admin/artworks">&larr; Artworks</a></p>
    <h1>Edit artwork</h1>
    <!-- Sales &amp; checkout controls are rendered by ArtworkSaleAdminForm. -->
    {$artworkPreview}
    <form method="post" action="/admin/artworks/edit">
        <input type="hidden" name="return_to" value="{$returnToValue}">
        <input type="hidden" name="id" value="{$id}">

        <section class="admin-card artwork-notes-editor">
            <h2>Public notes</h2>
            <p class="form-help">Shown on the public artwork detail page. Multiline HTML is allowed for trusted tenant-admin-authored notes.</p>
            <!-- ARTWORK_PUBLIC_NOTES_TOP_MARKER: public artwork note field belongs near the top of the edit form. -->
            <label>Public notes HTML
<textarea name="notes_html" rows="8" class="admin-textarea-wide">{$notesHtmlValue}</textarea></label>
        </section>

        <p><label>Title<br><input type="text" name="title" value="{$title}" required></label></p>
        <p><label>Date / year<br><input type="text" name="year_created" value="{$year}"></label></p>
        <p><label>Medium<br><input type="text" name="medium" value="{$medium}"></label></p>
        <p><label>Notes<br><textarea name="description" rows="6">{$notes}</textarea></label></p>
        <p>
            <label>Status<br>
                <select name="status">
                    <option value="draft"{$selected($status, 'draft')}>Draft</option>
                    <option value="published"{$selected($status, 'published')}>Published</option>
                    <option value="archived"{$selected($status, 'archived')}>Archived</option>
                </select>
            </label>
        </p>
        <p>
            <label>Sale status<br>
                <select name="sale_status">
                    <option value="nfs"{$selected($saleStatus, 'nfs')}>NFS</option>
                    <option value="for_sale"{$selected($saleStatus, 'for_sale')}>For sale</option>
                    <option value="sold"{$selected($saleStatus, 'sold')}>Sold</option>
                </select>
            </label>
        </p>
        <fieldset style="margin:1rem 0;padding:1rem;border:1px solid #ccc;">
            <legend>Artwork types</legend>
            <p>Choose whether this image is visible in the public portfolio, available for site image pickers, or both.</p>
            <label style="display:block;margin:.25rem 0;"><input type="checkbox" name="artwork_types[]" value="portfolio_images"{$portfolioChecked}> Portfolio Images</label>
            <label style="display:block;margin:.25rem 0;"><input type="checkbox" name="artwork_types[]" value="site_images"{$siteChecked}> Site Images</label>
        </fieldset>
        <fieldset style="margin:1rem 0;padding:1rem;border:1px solid #ccc;">
            <legend>Portfolio sections</legend>
            <p>Choose where this artwork appears.</p>
            {$sectionOptions}
        </fieldset>

{$saleFieldset}
        


<section class="admin-card artwork-internal-notes-editor">
            <h2>Internal notes</h2>
            <p class="form-help">Private administrative notes. These are not shown on the public artwork page.</p>
            <label>Internal notes
<textarea name="notes" rows="5" class="admin-textarea-wide">{$notesValue}</textarea></label>
        </section>

<button type="submit">Save artwork</button>
    </form>
</main>

<script src="/assets/admin/artworks.js"></script>
HTML;

        return Response::html(AdminLayout::render('Artworks', $body));
    }

    public function update(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        $this->rememberArtworkGridReturnUrl();


        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        $id = (int) ($_POST['id'] ?? 0);
        $artwork = $this->findArtwork($tenant, $id);

        if (!$artwork) {
            return Response::html('<h1>404</h1><p>Artwork not found.</p>', 404);
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            return Response::html('<h1>Invalid artwork</h1><p>Title is required.</p>', 422);
        }

        $status = in_array(($_POST['status'] ?? 'draft'), ['draft', 'published', 'archived'], true) ? (string) $_POST['status'] : 'draft';
        $saleStatus = in_array(($_POST['sale_status'] ?? 'nfs'), ['nfs', 'for_sale', 'sold'], true) ? (string) $_POST['sale_status'] : 'nfs';
        $price = $saleStatus === 'nfs' ? null : trim((string) ($_POST['price'] ?? ''));
        $notes = (string) ($_POST['notes'] ?? '');
        $notesHtml = (string) ($_POST['notes_html'] ?? '');
        $legacySalesInventory = (new ArtworkSaleAdminForm($this->pdo))->legacyInventoryFromPost($_POST);
        $isOneOff = $legacySalesInventory['is_one_off'];
        $inventoryQuantity = $legacySalesInventory['inventory_quantity'];

        $stmt = $this->pdo->prepare(
            "UPDATE artworks
             SET title = :title,
                 year_created = :year_created,
                 medium = :medium,
                 description = :description,
                 status = :status,
                 sale_status = :sale_status,
                 price = :price, notes = :notes, notes_html = :notes_html,
                 is_one_off = :is_one_off,
                 inventory_quantity = :inventory_quantity,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND tenant_id = :tenant_id"
        );

        try {
            $stmt->execute([
                'title' => $title,
                'year_created' => trim((string) ($_POST['year_created'] ?? '')) ?: null,
                'medium' => trim((string) ($_POST['medium'] ?? '')) ?: null,
                'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
                'status' => $status,
                'sale_status' => $saleStatus,
                'notes' => $notes,
                'notes_html' => $notesHtml,
                'price' => $price !== '' ? $price : null,
                'is_one_off' => $isOneOff,
                'inventory_quantity' => $inventoryQuantity,
                'id' => $id,
                'tenant_id' => $tenant->tenantId,
            ]);

            $this->replaceArtworkTypes($id, $_POST['artwork_types'] ?? []);
            $this->replaceArtworkSections($tenant, $id, $_POST['section_ids'] ?? []);
            try {
                try {
                    (new ArtworkSaleAdminForm($this->pdo))->saveFromPost($tenant->tenantId, $id, $_POST, $saleStatus);
                } catch (Throwable $e) {
                    error_log('ArtworkSaleAdminForm save failed: ' . $e->getMessage());
                    error_log('Artwork sales settings could not be saved for artwork update.');
                }
            } catch (Throwable $e) {
                error_log('ArtworkSaleAdminForm save failed: ' . $e->getMessage());
                return Response::html(
                    ErrorPage::render('Artwork sales settings could not be saved. Please try again.'),
                    500
                );
            }
        } catch (\Throwable $exception) {
            $this->logAdminArtworkEditFailure($tenant->tenantId, $id, 'Artwork admin update save failed', $exception);
            return Response::html('<h1>Artwork could not be saved</h1><p>The artwork update failed. The exact error has been written to <code>storage/logs/admin_artwork_edit.log</code>.</p><p><a href="/admin/artworks/edit?id=' . $id . '">Return to artwork editor</a></p>', 500);
        }

        $returnTo = (string) ($_POST['return_to'] ?? '/admin/artworks');
        if ($returnTo === '' || $returnTo[0] !== '/' || str_starts_with($returnTo, '//')) {
            $returnTo = '/admin/artworks';
        }
        $separator = str_contains($returnTo, '?') ? '&' : '?';
        return new Response('', 303, ['Location' => $returnTo . $separator . 'notice=artwork-saved#artwork-' . $id]);
    }



    public function updateDirectoryThumbnail(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {

        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        $id = (int) ($_POST['id'] ?? 0);
        $checked = isset($_POST['directory_thumbnail']);
        $returnTo = $this->safeReturnTo((string) ($_POST['return_to'] ?? '/admin/artworks'));
        $separator = str_contains($returnTo, '?') ? '&' : '?';

        if ($id <= 0) {
            return Response::html('<h1>Invalid artwork</h1>', 422);
        }

        if ($checked) {
            if (!$this->isValidDirectoryThumbnailArtwork($tenant, $id)) {
                return Response::html('<h1>Invalid directory thumbnail</h1><p>Choose a published artwork with a primary image.</p>', 422);
            }

            $this->setTenantSetting($tenant, 'platform_directory_thumbnail_artwork_id', (string) $id);
        } else {
            $current = (int) $this->getTenantSetting($tenant, 'platform_directory_thumbnail_artwork_id', '0');
            if ($current === $id) {
                $this->setTenantSetting($tenant, 'platform_directory_thumbnail_artwork_id', '');
            }
        }

        return new Response('', 303, ['Location' => $returnTo . $separator . 'notice=directory-thumbnail-updated#artwork-' . $id]);
    }

    public function updateStatus(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {

        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        $id = (int) ($_POST['id'] ?? 0);
        $status = (string) ($_POST['status'] ?? 'draft');

        if (!in_array($status, ['draft', 'published', 'archived'], true)) {
            return Response::html('<h1>Invalid status</h1>', 422);
        }

        $stmt = $this->pdo->prepare(
            "UPDATE artworks
             SET status = :status,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND tenant_id = :tenant_id"
        );

        $stmt->execute([
            'status' => $status,
            'id' => $id,
            'tenant_id' => $tenant->tenantId,
        ]);
        (new TenantDirectoryProfileRepository($this->pdo))->syncTenant($tenant->tenantId);

        $returnTo = $this->safeReturnTo((string) ($_POST['return_to'] ?? '/admin/artworks'));
        $separator = str_contains($returnTo, '?') ? '&' : '?';

        if ($this->wantsJson()) {
            return new Response(json_encode([
                'ok' => true,
                'id' => $id,
                'status' => $status,
                'next_status' => $status === 'published' ? 'draft' : 'published',
                'next_label' => $status === 'published' ? 'Unpublish' : 'Publish',
                'message' => 'Artwork status updated.',
            ], JSON_THROW_ON_ERROR), 200, ['Content-Type' => 'application/json']);
        }

        return new Response('', 303, ['Location' => $returnTo . $separator . 'notice=status-updated#artwork-' . $id]);
    }


    public function delete(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {

        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            return Response::html('<h1>Invalid artwork</h1>', 422);
        }

        $stmt = $this->pdo->prepare(
            "UPDATE artworks
             SET status = 'archived',
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND tenant_id = :tenant_id"
        );

        $stmt->execute([
            'id' => $id,
            'tenant_id' => $tenant->tenantId,
        ]);
        (new TenantDirectoryProfileRepository($this->pdo))->syncTenant($tenant->tenantId);

        $returnTo = $this->safeReturnTo((string) ($_POST['return_to'] ?? '/admin/artworks'));
        $separator = str_contains($returnTo, '?') ? '&' : '?';

        if ($this->wantsJson()) {
            return new Response(json_encode([
                'ok' => true,
                'id' => $id,
                'archived' => true,
                'message' => 'Artwork archived.',
            ], JSON_THROW_ON_ERROR), 200, ['Content-Type' => 'application/json']);
        }

        return new Response('', 303, ['Location' => $returnTo . $separator . 'notice=artwork-archived']);
    }


    /**
     * Render one status action button for the current artwork state.
     *
     * Published artwork should only offer Unpublish. Draft artwork should only
     * offer Publish. Archived artwork is already hidden by the index query, but
     * the defensive fallback keeps future list changes from rendering nonsense.
     *
     * @param array<string,mixed> $row
     */
    private function statusActionButton(array $row, string $returnToValue): string
    {
        $id = (int) ($row['id'] ?? 0);
        $status = (string) ($row['status'] ?? 'draft');

        if ($id <= 0 || $status === 'archived') {
            return '';
        }

        $nextStatus = $status === 'published' ? 'draft' : 'published';
        $label = $status === 'published' ? 'Unpublish' : 'Publish';
        $escapedNextStatus = htmlspecialchars($nextStatus, ENT_QUOTES, 'UTF-8');
        $escapedLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <form method="post" action="/admin/artworks/status" class="js-artwork-action" style="display:inline">
            <input type="hidden" name="id" value="{$id}">
            <input type="hidden" name="status" value="{$escapedNextStatus}">
            <input type="hidden" name="return_to" value="{$returnToValue}">
            <button type="submit">{$escapedLabel}</button>
        </form>
HTML;
    }

    private function wantsJson(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch'
            || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    private function safeReturnTo(string $returnTo): string
    {
        if ($returnTo === '' || !str_starts_with($returnTo, '/admin/artworks')) {
            return '/admin/artworks';
        }

        return $returnTo;
    }

    private function artworkTypeBadges(string $codes): string
    {
        $labels = [];
        foreach (array_filter(explode(',', $codes)) as $code) {
            $labels[] = match ($code) {
                'portfolio_images' => 'Portfolio Images',
                'site_images' => 'Site Images',
                default => $code,
            };
        }

        return $labels ? '<small>' . htmlspecialchars(implode(' · ', $labels), ENT_QUOTES, 'UTF-8') . '</small>' : '<small>No type</small>';
    }

    /**
     * @return list<string>
     */
    private function artworkTypeCodes(int $artworkId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT atype.code
             FROM artwork_type_assignments ata
             JOIN artwork_types atype ON atype.id = ata.type_id
             WHERE ata.artwork_id = :artwork_id"
        );
        $stmt->execute(['artwork_id' => $artworkId]);

        return array_map('strval', array_column($stmt->fetchAll(), 'code'));
    }

    /**
     * @param mixed $rawTypes
     */
    private function replaceArtworkTypes(int $artworkId, mixed $rawTypes): void
    {
        $allowed = ['portfolio_images', 'site_images'];
        $codes = [];
        if (is_array($rawTypes)) {
            foreach ($rawTypes as $code) {
                $code = (string) $code;
                if (in_array($code, $allowed, true)) {
                    $codes[] = $code;
                }
            }
        }
        $codes = array_values(array_unique($codes));

        $this->pdo->prepare('DELETE FROM artwork_type_assignments WHERE artwork_id = :artwork_id')->execute(['artwork_id' => $artworkId]);
        if (!$codes) {
            return;
        }

        $lookup = $this->pdo->prepare('SELECT id FROM artwork_types WHERE code = :code LIMIT 1');
        $insert = $this->pdo->prepare('INSERT IGNORE INTO artwork_type_assignments (artwork_id, type_id, created_at) VALUES (:artwork_id, :type_id, CURRENT_TIMESTAMP)');

        foreach ($codes as $code) {
            $lookup->execute(['code' => $code]);
            $row = $lookup->fetch();
            if (!$row) {
                continue;
            }
            $insert->execute(['artwork_id' => $artworkId, 'type_id' => (int) $row['id']]);
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function portfolioSections(TenantContext $tenant): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, slug
             FROM portfolio_sections WHERE tenant_id = :tenant_id AND status <> 'archived' ORDER BY LOWER(name), name, id"
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId]);

        return $stmt->fetchAll();
    }

    /**
     * @return list<int>
     */
    private function artworkSectionIds(TenantContext $tenant, int $artworkId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT asa.section_id
             FROM artwork_section_assignments asa
             JOIN portfolio_sections ps ON ps.id = asa.section_id
             WHERE asa.artwork_id = :artwork_id
               AND ps.tenant_id = :tenant_id ORDER BY LOWER(ps.name) ASC, ps.name ASC, ps.id ASC"
        );
        $stmt->execute([
            'artwork_id' => $artworkId,
            'tenant_id' => $tenant->tenantId,
        ]);

        return array_map('intval', array_column($stmt->fetchAll(), 'section_id'));
    }

    /**
     * @param mixed $rawSectionIds
     */
    private function replaceArtworkSections(TenantContext $tenant, int $artworkId, mixed $rawSectionIds): void
    {
        $sectionIds = [];

        if (is_array($rawSectionIds)) {
            foreach ($rawSectionIds as $sectionId) {
                $sectionId = (int) $sectionId;
                if ($sectionId > 0) {
                    $sectionIds[] = $sectionId;
                }
            }
        }

        $sectionIds = array_values(array_unique($sectionIds));

        $delete = $this->pdo->prepare('DELETE FROM artwork_section_assignments WHERE artwork_id = :artwork_id');
        $delete->execute(['artwork_id' => $artworkId]);

        if (!$sectionIds) {
            return;
        }

        $valid = $this->pdo->prepare(
            "SELECT id
             FROM portfolio_sections
             WHERE tenant_id = :tenant_id
               AND id = :id
               AND status <> 'archived'
             LIMIT 1"
        );
        $insert = $this->pdo->prepare(
            "INSERT INTO artwork_section_assignments (artwork_id, section_id, sort_order, created_at)
             VALUES (:artwork_id, :section_id, 0, CURRENT_TIMESTAMP)"
        );

        foreach ($sectionIds as $sectionId) {
            $valid->execute([
                'tenant_id' => $tenant->tenantId,
                'id' => $sectionId,
            ]);

            if (!$valid->fetch()) {
                continue;
            }

            $insert->execute([
                'artwork_id' => $artworkId,
                'section_id' => $sectionId,
            ]);
        }
    }


    private function isValidDirectoryThumbnailArtwork(TenantContext $tenant, int $artworkId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.id
             FROM artworks a
             JOIN media_assets m ON m.id = a.primary_media_id AND m.tenant_id = a.tenant_id
             WHERE a.tenant_id = :tenant_id
               AND a.id = :artwork_id
               AND a.status = 'published'
               AND a.primary_media_id IS NOT NULL
             LIMIT 1"
        );
        $stmt->execute([
            'tenant_id' => $tenant->tenantId,
            'artwork_id' => $artworkId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function getTenantSetting(TenantContext $tenant, string $key, string $default = ''): string
    {
        $stmt = $this->pdo->prepare(
            "SELECT setting_value
             FROM tenant_settings
             WHERE tenant_id = :tenant_id
               AND setting_key = :setting_key
             LIMIT 1"
        );
        $stmt->execute([
            'tenant_id' => $tenant->tenantId,
            'setting_key' => $key,
        ]);

        $value = $stmt->fetchColumn();

        return $value === false || $value === null ? $default : (string) $value;
    }

    private function setTenantSetting(TenantContext $tenant, string $key, string $value): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, updated_at)
             VALUES (:tenant_id, :setting_key, :setting_value, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([
            'tenant_id' => $tenant->tenantId,
            'setting_key' => $key,
            'setting_value' => $value,
        ]);

        if ($key === 'platform_directory_thumbnail_artwork_id') {
            (new TenantDirectoryProfileRepository($this->pdo))->syncTenant($tenant->tenantId);
        }
    }

    private function findArtwork(TenantContext $tenant, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT a.*,
                    m.uuid AS primary_media_uuid,
                    m.mime_type AS primary_media_mime_type,
                    m.width AS primary_media_width,
                    m.height AS primary_media_height
             FROM artworks a
             LEFT JOIN media_assets m ON m.id = a.primary_media_id
             WHERE a.id = :id
               AND a.tenant_id = :tenant_id
             LIMIT 1"
        );

        $stmt->execute([
            'id' => $id,
            'tenant_id' => $tenant->tenantId,
        ]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function logAdminArtworkEditFailure(int $tenantId, int $artworkId, string $message, \Throwable $exception): void
    {
        $payload = [
            'tenant_id' => $tenantId,
            'artwork_id' => $artworkId,
            'message' => $message,
            'exception' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];
        $line = '[ArtsFolio admin artwork edit] ' . json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL;
        error_log(rtrim($line));

        $root = dirname(__DIR__, 5);
        $logDir = $root . '/storage/logs';
        if (is_dir($logDir) || @mkdir($logDir, 0775, true)) {
            if (@file_put_contents($logDir . '/admin_artwork_edit.log', $line, FILE_APPEND | LOCK_EX) !== false) {
                return;
            }
        }

        @file_put_contents('/tmp/artsfolio_admin_artwork_edit.log', $line, FILE_APPEND | LOCK_EX);
    }


    /**
     * Keep artwork save redirects on-site and tenant-admin scoped.
     */
    private function safeArtworkReturnTo(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '/admin/artworks';
        }
        if (str_starts_with($value, '/admin/artworks')) {
            return $value;
        }
        return '/admin/artworks';
    }

    /**
     * Add or replace a notice query parameter without discarding the grid page/filter.
     */
    private function artworkReturnWithNotice(string $returnTo, string $notice): string
    {
        $separator = str_contains($returnTo, '?') ? '&' : '?';
        return $returnTo . $separator . 'notice=' . rawurlencode($notice);
    }


    /**
     * Current admin artwork grid URL used as the return target from edit pages.
     */
    private function currentArtworkAdminUri(): string
    {
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/admin/artworks');
        if (!str_starts_with($requestUri, '/admin/artworks')) {
            return '/admin/artworks';
        }
        return $requestUri;
    }

    private function rememberArtworkGridReturnUrl(): void
    {
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            return;
        }

        $candidates = [
            $_POST['return_to'] ?? null,
            $_GET['return_to'] ?? null,
            $_SERVER['REQUEST_URI'] ?? null,
            $_SERVER['HTTP_REFERER'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }

            $normalized = $this->normalizeArtworkGridReturnUrl($candidate);

            if ($normalized !== null) {
                $_SESSION['tenant_admin_artworks_return_to'] = $normalized;
                return;
            }
        }
    }



    private function artworkGridReturnUrl(): string
    {
        $candidates = [
            $_POST['return_to'] ?? null,
            $_GET['return_to'] ?? null,
            $_SESSION['tenant_admin_artworks_return_to'] ?? null,
            $_SERVER['HTTP_REFERER'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }

            $normalized = $this->normalizeArtworkGridReturnUrl($candidate);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return '/admin/artworks';
    }



    private function normalizeArtworkGridReturnUrl(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        if (str_contains($url, "\r") || str_contains($url, "\n")) {
            return null;
        }

        if (str_starts_with($url, '//')) {
            return null;
        }

        $parts = parse_url($url);

        if (!is_array($parts)) {
            return null;
        }

        $path = $parts['path'] ?? null;

        if ($path !== '/admin/artworks') {
            return null;
        }

        $normalized = '/admin/artworks';

        if (isset($parts['query']) && is_string($parts['query']) && $parts['query'] !== '') {
            $normalized .= '?' . $parts['query'];
        }

        return $normalized;
    }

    private function artworkGridCurrentReturnParam(): string
    {
        $current = $this->artworkGridReturnUrl();

        if ($current === '/admin/artworks') {
            $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
            $normalized = $this->normalizeArtworkGridReturnUrl($requestUri);

            if ($normalized !== null) {
                $current = $normalized;
            }
        }

        return rawurlencode($current);
    }

}

// End of file.
