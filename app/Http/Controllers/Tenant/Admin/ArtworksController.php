<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;


use App\Http\View\ErrorPage;
use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Tenancy\TenantContext;
use App\Platform\Audit\AuditLogRepository;
use PDO;
use App\Http\View\AdminLayout;

final class ArtworksController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly PDO $pdo,
        private readonly AuditLogRepository $auditLog,
    ) {
    }

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        $q = trim((string) ($_GET['q'] ?? ''));
        $sort = (string) ($_GET['sort'] ?? 'created_desc');

        $orderBy = match ($sort) {
            'name' => 'a.title ASC, a.id DESC',
            'medium' => 'a.medium ASC, a.title ASC',
            'date' => 'a.year_created DESC, a.title ASC',
            'status' => 'a.status ASC, a.title ASC',
            default => 'a.id DESC',
        };

        $where = "a.tenant_id = :tenant_id AND a.status <> 'archived'";
        $params = ['tenant_id' => $tenant->tenantId];

        if ($q !== '') {
            $where .= " AND (a.title LIKE :q OR a.medium LIKE :q)";
            $params['q'] = '%' . $q . '%';
        }

        $stmt = $this->pdo->prepare(
            "SELECT
                a.id,
                a.title,
                a.slug,
                a.description,
                a.medium,
                a.year_created,
                a.status,
                a.sale_status,
                a.price,
                COALESCE(a.is_one_off, 1) AS is_one_off,
                COALESCE(a.inventory_quantity, 1) AS inventory_quantity,
                (SELECT ts.setting_value
                 FROM tenant_settings ts
                 WHERE ts.tenant_id = a.tenant_id
                   AND ts.setting_key = 'platform_directory_thumbnail_artwork_id'
                 LIMIT 1) AS directory_thumbnail_artwork_id,
                a.created_at,
                (SELECT GROUP_CONCAT(atype.code ORDER BY atype.code SEPARATOR ',')
                 FROM artwork_type_assignments ata
                 JOIN artwork_types atype ON atype.id = ata.type_id
                 WHERE ata.artwork_id = a.id) AS artwork_type_codes,
                m.id AS media_id,
                m.uuid AS media_uuid,
                m.storage_path,
                m.mime_type,
                m.width,
                m.height
             FROM artworks a
             LEFT JOIN media_assets m ON m.id = a.primary_media_id
             WHERE {$where}
             ORDER BY {$orderBy}
             LIMIT 240"
        );

        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $returnTo = '/admin/artworks';
        $queryString = http_build_query(array_filter([
            'q' => $q,
            'sort' => $sort,
        ], static fn ($value): bool => $value !== ''));

        if ($queryString !== '') {
            $returnTo .= '?' . $queryString;
        }

        $returnToValue = htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8');
        $items = '';
        $queryValue = htmlspecialchars($q, ENT_QUOTES, 'UTF-8');
        $sortOption = fn (string $value): string => $sort === $value ? ' selected' : '';

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
                $src = '/admin/media?uuid=' . rawurlencode((string) $row['media_uuid']);
                $src = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
                $image = "<img src=\"{$src}\" alt=\"{$title}\" style=\"max-width:180px;max-height:140px;object-fit:contain;border:1px solid #ddd;background:#fff;\">";
            }

            $items .= <<<HTML
<tr id="artwork-{$row['id']}">
    <td>{$image}</td>
    <td>
        <strong>{$title}</strong><br>
        <small>ID {$row['id']} · {$created}</small>
    </td>
    <td>{$year}</td>
    <td>{$medium}</td>
    <td class="js-artwork-status">{$status}<br>{$typeBadges}</td>
    <td>{$saleStatus}</td>
    <td>{$price}</td>
    <td>{$notes}</td>
    <td>
        <form method="post" action="/admin/artworks/directory-thumbnail" class="directory-thumbnail-form">
            <input type="hidden" name="id" value="{$artworkId}">
            <input type="hidden" name="return_to" value="{$returnToValue}">
            <label class="directory-thumbnail-toggle">
                <input type="checkbox" name="directory_thumbnail" value="1"{$directoryChecked}{$directoryDisabled} onchange="this.form.submit()">
                <span>Directory thumbnail</span>
            </label>
            <small>{$directoryHelp}</small>
            <noscript><button type="submit">Save</button></noscript>
        </form>
    </td>
    <td>
        <a href="/admin/artworks/edit?id={$row['id']}">Edit</a>
        {$this->statusActionButton($row, $returnToValue)}
        <form method="post" action="/admin/artworks/delete" class="js-artwork-action" style="display:inline" onsubmit="return confirm('Archive this artwork? It will disappear from normal review and public pages, but the file will not be deleted yet.');">
            <input type="hidden" name="id" value="{$row['id']}">
            <input type="hidden" name="return_to" value="{$returnToValue}">
            <button type="submit">Archive</button>
        </form>
    </td>
</tr>
HTML;
        }

        if ($items === '') {
            $items = '<tr><td colspan="10">No artwork uploaded yet.</td></tr>';
        }

        $notice = match ((string) ($_GET['notice'] ?? '')) {
            'status-updated' => '<p style="padding:.75rem;background:#eef8ee;border:1px solid #9ac99a;">Artwork status updated.</p>',
            'artwork-archived' => '<p style="padding:.75rem;background:#fff4df;border:1px solid #d9b36a;">Artwork archived.</p>',
            'artwork-saved' => '<p style="padding:.75rem;background:#eef8ee;border:1px solid #9ac99a;">Artwork saved.</p>',
            'directory-thumbnail-updated' => '<p style="padding:.75rem;background:#eef8ee;border:1px solid #9ac99a;">Directory thumbnail updated.</p>',
            default => '',
        };

        $body = <<<HTML
<main>
    <div id="artwork-action-notice">{$notice}</div>
    <p><a href="/admin/artwork/upload">Upload artwork</a> · <a href="/admin/artworks/placement">Artwork placement matrix</a> · <a href="/admin/portfolio-sections/order">Section artwork order</a></p>

    <form method="get" action="/admin/artworks" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:end;margin:1rem 0;">
        <label>Filter by name or medium<br>
            <input type="search" name="q" value="{$queryValue}">
        </label>
        <label>Sort<br>
            <select name="sort">
                <option value="created_desc"{$sortOption('created_desc')}>Newest uploaded</option>
                <option value="name"{$sortOption('name')}>Name</option>
                <option value="medium"{$sortOption('medium')}>Medium</option>
                <option value="date"{$sortOption('date')}>Date/year</option>
                <option value="status"{$sortOption('status')}>Status</option>
            </select>
        </label>
        <button type="submit">Apply</button>
        <a href="/admin/artworks">Clear</a>
    </form>

    <table border="1" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <th>Image</th>
                <th>Title</th>
                <th>Date/year</th>
                <th>Medium</th>
                <th>Status</th>
                <th>Sale</th>
                <th>Price</th>
                <th>Notes</th>
                <th>Directory thumbnail</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            {$items}
        </tbody>
    </table>
</main>

<script src="/assets/admin/artworks.js"></script>
HTML;

        return Response::html(AdminLayout::render('Artworks', $body));
    }

    public function edit(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
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
        $selectedSectionIds = $this->artworkSectionIds($tenant, $id);
        $selectedTypeCodes = $this->artworkTypeCodes($id);
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

        $body = <<<HTML
<main>
    <p><a href="/admin/artworks">&larr; Artworks</a></p>
    <h1>Edit artwork</h1>
    <form method="post" action="/admin/artworks/edit">
        <input type="hidden" name="id" value="{$id}">
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
        <p><label>Price<br><input type="text" name="price" value="{$price}"></label></p>
        <fieldset style="margin:1rem 0;padding:1rem;border:1px solid #ccc;">
            <legend>Sales inventory</legend>
            <p>Mark one-off for traditional original work. Use multiple for editioned objects, postcards, shirts, or other inventory-backed items.</p>
            <label style="display:block;margin:.25rem 0;"><input type="radio" name="sales_inventory_mode" value="one_off"{$oneOffChecked}> One-off artwork</label>
            <label style="display:block;margin:.25rem 0;"><input type="radio" name="sales_inventory_mode" value="multiple"{$multipleChecked}> Multiple / inventory item</label>
            <label style="display:block;margin:.5rem 0;">Inventory quantity<br><input type="number" name="inventory_quantity" min="1" step="1" value="{$inventoryQuantity}"></label>
        </fieldset>
        <button type="submit">Save artwork</button>
    </form>
</main>

<script src="/assets/admin/artworks.js"></script>
HTML;

        return Response::html(AdminLayout::render('Artworks', $body));
    }

    public function update(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
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
        $salesInventoryMode = (string) ($_POST['sales_inventory_mode'] ?? 'one_off');
        $isOneOff = $salesInventoryMode === 'multiple' ? 0 : 1;
        $inventoryQuantity = max(1, (int) ($_POST['inventory_quantity'] ?? 1));
        if ($isOneOff === 1) {
            $inventoryQuantity = 1;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE artworks
             SET title = :title,
                 year_created = :year_created,
                 medium = :medium,
                 description = :description,
                 status = :status,
                 sale_status = :sale_status,
                 price = :price,
                 is_one_off = :is_one_off,
                 inventory_quantity = :inventory_quantity,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND tenant_id = :tenant_id"
        );

        $stmt->execute([
            'title' => $title,
            'year_created' => trim((string) ($_POST['year_created'] ?? '')) ?: null,
            'medium' => trim((string) ($_POST['medium'] ?? '')) ?: null,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            'status' => $status,
            'sale_status' => $saleStatus,
            'price' => $price !== '' ? $price : null,
            'is_one_off' => $isOneOff,
            'inventory_quantity' => $inventoryQuantity,
            'id' => $id,
            'tenant_id' => $tenant->tenantId,
        ]);

        $this->replaceArtworkTypes($id, $_POST['artwork_types'] ?? []);
        $this->replaceArtworkSections($tenant, $id, $_POST['section_ids'] ?? []);

        return new Response('', 303, ['Location' => '/admin/artworks?notice=artwork-saved#artwork-' . $id]);
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
             FROM portfolio_sections
             WHERE tenant_id = :tenant_id
               AND status <> 'archived'
             ORDER BY sort_order ASC, name ASC"
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
               AND ps.tenant_id = :tenant_id"
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
    }

    private function findArtwork(TenantContext $tenant, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM artworks
             WHERE id = :id
               AND tenant_id = :tenant_id
             LIMIT 1"
        );

        $stmt->execute([
            'id' => $id,
            'tenant_id' => $tenant->tenantId,
        ]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

}

// End of file.
