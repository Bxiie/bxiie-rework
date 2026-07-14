<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;


use App\Http\View\ErrorPage;
use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use PDO;
use App\Http\View\AdminLayout;

/**
 * Tenant admin management for public portfolio sections and top tabs.
 */
final class PortfolioSectionsController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly PDO $pdo,
        private readonly CsrfTokenService $csrf,
    ) {
    }

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser, $tenant)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, name, slug, description, show_as_tab, sort_order, status, created_at, updated_at
             FROM portfolio_sections
             WHERE tenant_id = :tenant_id
               AND status <> 'archived'
             ORDER BY sort_order ASC, name ASC"
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId]);

        $rows = '';
        foreach ($stmt->fetchAll() as $section) {
            $id = (int) $section['id'];
            $name = $this->escape((string) $section['name']);
            $slug = $this->escape((string) $section['slug']);
            $description = $this->escape((string) ($section['description'] ?? ''));
            $showAsTab = ((int) $section['show_as_tab']) === 1 ? 'Yes' : 'No';
            $sortOrder = $this->escape((string) $section['sort_order']);
            $status = $this->escape((string) $section['status']);

            $rows .= <<<HTML
<tr>
    <td>{$sortOrder}</td>
    <td><strong>{$name}</strong><br><small>/portfolio?section={$slug}</small></td>
    <td>{$description}</td>
    <td>{$showAsTab}</td>
    <td>{$status}</td>
    <td>
        <a href="/admin/portfolio-sections/edit?id={$id}">Edit</a>
    </td>
</tr>
HTML;
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6">No portfolio sections yet.</td></tr>';
        }

        $notice = match ((string) ($_GET['notice'] ?? '')) {
            'saved' => '<p class="notice" style="padding:.75rem;background:#eef8ee;border:1px solid #9ac99a;">Portfolio section saved.</p>',
            'archived' => '<p class="notice" style="padding:.75rem;background:#fff4df;border:1px solid #d9b36a;">Portfolio section archived.</p>',
            default => '',
        };

        $body = <<<HTML
<main class="admin-main" style="max-width:1100px;margin:2rem auto;padding:0 1rem;">
    <p><a href="/admin">&larr; Admin</a></p>
    <h1>Portfolio Sections</h1>
    {$notice}
    <p>Use sections to group artwork and optionally show selected sections as public portfolio tabs.</p>
    <section aria-label="Portfolio section actions" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin:1.25rem 0 1.5rem;">
        <a class="admin-button" href="/admin/portfolio-sections/edit" style="display:flex;flex-direction:column;align-items:flex-start;gap:.35rem;padding:1rem 1.1rem;border-radius:14px;text-decoration:none;">
            <strong>Add portfolio section</strong>
            <span style="font-size:.9rem;font-weight:500;opacity:.85;">Create a new public artwork grouping.</span>
        </a>
        <a class="admin-button" href="/admin/portfolio-sections/order" style="display:flex;flex-direction:column;align-items:flex-start;gap:.35rem;padding:1rem 1.1rem;border-radius:14px;text-decoration:none;">
            <strong>Order artwork in sections and home page</strong>
            <span style="font-size:.9rem;font-weight:500;opacity:.85;">Set the display order within each section.</span>
        </a>
        <a class="admin-button" href="/admin/artworks/placement" style="display:flex;flex-direction:column;align-items:flex-start;gap:.35rem;padding:1rem 1.1rem;border-radius:14px;text-decoration:none;">
            <strong>Artwork placement matrix</strong>
            <span style="font-size:.9rem;font-weight:500;opacity:.85;">Assign many artworks to sections at once.</span>
        </a>
    </section>
    <table class="admin-table" border="1" cellpadding="8" cellspacing="0" style="width:100%;border-collapse:collapse;">
        <thead>
            <tr>
                <th>Order</th>
                <th>Section</th>
                <th>Description</th>
                <th>Show as tab</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            {$rows}
        </tbody>
    </table>
</main>
HTML;

        return Response::html(AdminLayout::render('Portfolio Sections', $body));
    }

    public function edit(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser, $tenant)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        $id = (int) ($_GET['id'] ?? 0);
        $section = $id > 0 ? $this->find($tenant, $id) : null;

        if ($id > 0 && !$section) {
            return Response::html('<h1>404</h1><p>Portfolio section not found.</p>', 404);
        }

        $token = $this->escape($this->csrf->getOrCreate());
        $name = $this->escape((string) ($section['name'] ?? ''));
        $slug = $this->escape((string) ($section['slug'] ?? ''));
        $description = $this->escape((string) ($section['description'] ?? ''));
        $sortOrder = $this->escape((string) ($section['sort_order'] ?? '100'));
        $status = (string) ($section['status'] ?? 'active');
        $showAsTab = ((int) ($section['show_as_tab'] ?? 0)) === 1;

        $selected = fn (string $value): string => $status === $value ? ' selected' : '';
        $checked = $showAsTab ? ' checked' : '';

        $isAdmin = $this->isTenantAdmin($currentUser, $tenant);
        $archiveButton = $isAdmin && $id > 0
            ? '<button type="submit" name="archive" value="1" onclick="return confirm(\'Archive this portfolio section? Existing artwork assignments will remain in the database but this section will no longer appear.\');">Archive section</button>'
            : '';
        $publicationControls = $isAdmin
            ? '<p><label><input type="checkbox" name="show_as_tab" value="1"' . $checked . '> Show this section as a public portfolio tab</label></p>'
                . '<p><label>Status<br><select name="status">'
                . '<option value="active"' . $selected('active') . '>Active</option>'
                . '<option value="hidden"' . $selected('hidden') . '>Hidden</option>'
                . '<option value="archived"' . $selected('archived') . '>Archived</option>'
                . '</select></label></p>'
            : '<input type="hidden" name="status" value="hidden">'
                . '<p class="admin-notice">This section will be saved as a draft. An administrator can review and publish it.</p>';

        $body = <<<HTML
<main class="admin-main" style="max-width:760px;margin:2rem auto;padding:0 1rem;">
    <p><a href="/admin/portfolio-sections">&larr; Portfolio sections</a></p>
    <h1>Edit Portfolio Section</h1>
    <form method="post" action="/admin/portfolio-sections/edit">
        <input type="hidden" name="csrf_token" value="{$token}">
        <input type="hidden" name="id" value="{$id}">
        <p><label>Name<br><input type="text" name="name" value="{$name}" required style="width:100%"></label></p>
        <p><label>Slug<br><input type="text" name="slug" value="{$slug}" placeholder="auto-created from name if blank" style="width:100%"></label></p>
        <p><label>Description<br><textarea name="description" rows="5" style="width:100%">{$description}</textarea></label></p>
        <p><label>Sort order<br><input type="number" name="sort_order" value="{$sortOrder}" style="width:10rem"></label></p>
        {$publicationControls}
        <p style="display:flex;gap:.75rem;flex-wrap:wrap;">
            <button type="submit">Save section</button>
            {$archiveButton}
        </p>
        <button type="submit" class="button button-primary">Save order</button>
</form>
</main>
HTML;

        return Response::html(AdminLayout::render('Portfolio Sections', $body));
    }

    public function update(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser, $tenant)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::html('<h1>Invalid request</h1><p>CSRF token failed.</p>', 419);
        }

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $id > 0 ? $this->find($tenant, $id) : null;
        $isAdmin = $this->isTenantAdmin($currentUser, $tenant);

        if (!$isAdmin && $existing && (string) ($existing['status'] ?? '') !== 'hidden') {
            return Response::html(
                '<h1>Draft access only</h1><p>Contributors cannot alter an active portfolio section.</p>',
                403,
            );
        }

        if ($id > 0 && !$existing) {
            return Response::html('<h1>404</h1><p>Portfolio section not found.</p>', 404);
        }

        if (isset($_POST['archive']) && $existing) {
            $stmt = $this->pdo->prepare(
                "UPDATE portfolio_sections
                 SET status = 'archived', updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND tenant_id = :tenant_id"
            );
            $stmt->execute(['id' => $id, 'tenant_id' => $tenant->tenantId]);

            return new Response('', 303, ['Location' => '/admin/portfolio-sections?notice=archived']);
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            return Response::html('<h1>Invalid section</h1><p>Name is required.</p>', 422);
        }

        $slug = $this->safeSlug((string) ($_POST['slug'] ?? ''), $name);
        $description = trim((string) ($_POST['description'] ?? '')) ?: null;
        $isAdmin = $this->isTenantAdmin($currentUser, $tenant);
        $showAsTab = $isAdmin && isset($_POST['show_as_tab']) ? 1 : 0;
        $sortOrder = (int) ($_POST['sort_order'] ?? 100);
        $status = $isAdmin
            ? (in_array((string) ($_POST['status'] ?? 'active'), ['active', 'hidden', 'archived'], true) ? (string) $_POST['status'] : 'active')
            : 'hidden';

        if ($existing) {
            $stmt = $this->pdo->prepare(
                "UPDATE portfolio_sections
                 SET name = :name,
                     slug = :slug,
                     description = :description,
                     show_as_tab = :show_as_tab,
                     sort_order = :sort_order,
                     status = :status,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id
                   AND tenant_id = :tenant_id"
            );
            $stmt->execute([
                'id' => $id,
                'tenant_id' => $tenant->tenantId,
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'show_as_tab' => $showAsTab,
                'sort_order' => $sortOrder,
                'status' => $status,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                "INSERT INTO portfolio_sections (
                    uuid, tenant_id, name, slug, description, show_as_tab, sort_order, status, created_at, updated_at
                 ) VALUES (
                    UUID(), :tenant_id, :name, :slug, :description, :show_as_tab, :sort_order, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                 )"
            );
            $stmt->execute([
                'tenant_id' => $tenant->tenantId,
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'show_as_tab' => $showAsTab,
                'sort_order' => $sortOrder,
                'status' => $status,
            ]);
        }

        return new Response('', 303, ['Location' => '/admin/portfolio-sections?notice=saved']);
    }

    private function find(TenantContext $tenant, int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM portfolio_sections WHERE tenant_id = :tenant_id AND id = :id LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function canManage(?array $currentUser, TenantContext $tenant): bool
    {
        return $this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin', 'editor', 'user']);
    }

    private function isTenantAdmin(?array $currentUser, TenantContext $tenant): bool
    {
        return $this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin']);
    }

    private function safeSlug(string $slug, string $fallback): string
    {
        $source = trim($slug) !== '' ? $slug : $fallback;
        $slug = strtolower(trim($source));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'section';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
