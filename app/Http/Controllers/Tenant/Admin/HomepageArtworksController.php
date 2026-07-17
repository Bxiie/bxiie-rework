<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Http\View\ErrorPage;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use PDO;

/** Manages the virtual Home Page portfolio section. */
final class HomepageArtworksController
{
    // HOME_PAGE_ALLOWS_DRAFT_ARTWORK: public visibility follows normal portfolio status behavior.
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

        $statement = $this->pdo->prepare(
            'SELECT a.id, a.title, a.slug, a.status,
                    CASE WHEN h.id IS NULL THEN 0 ELSE 1 END AS selected,
                    COALESCE(h.sort_order, a.sort_order, 0) AS home_sort_order
             FROM artworks a
             LEFT JOIN homepage_artwork_assignments h
               ON h.tenant_id = a.tenant_id
              AND h.artwork_id = a.id
             WHERE a.tenant_id = :tenant_id
               AND NOT EXISTS (
                    SELECT 1
                    FROM artwork_type_assignments ata
                    JOIN artwork_types at ON at.id = ata.type_id
                    WHERE ata.artwork_id = a.id
                      AND at.code = "site"
               )
             ORDER BY CASE WHEN h.id IS NULL THEN 1 ELSE 0 END,
                      COALESCE(h.sort_order, a.sort_order, 0), a.title'
        );
        $statement->execute(['tenant_id' => $tenant->tenantId]);

        $rows = '';
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $artwork) {
            $id = (int) $artwork['id'];
            $checked = (int) $artwork['selected'] === 1 ? ' checked' : '';
            $title = $this->e((string) $artwork['title']);
            $status = $this->e((string) $artwork['status']);
            $order = max(0, (int) $artwork['home_sort_order']);
            $disabled = $artwork['status'] === 'published' ? '' : ' disabled';

            $rows .= '<tr>'
                . '<td><input type="checkbox" name="artwork_ids[]" value="' . $id . '"' . $checked . $disabled . '></td>'
                . '<td><strong>' . $title . '</strong><br><span class="admin-muted">' . $status . '</span></td>'
                . '<td><input type="number" min="0" step="10" name="sort_order[' . $id . ']" value="' . $order . '" style="width:7rem"' . $disabled . '></td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="3">No portfolio artworks are available.</td></tr>';
        }

        $notice = (string) ($_GET['notice'] ?? '') === 'saved'
            ? '<p class="admin-notice admin-notice-success">Home Page artwork selection saved.</p>'
            : '';
        $csrf = $this->e($this->csrf->getOrCreate());

        $body = <<<HTML
{$notice}
<p><a href="/admin/portfolio-sections">← Back to Portfolio Sections</a></p>
<section class="admin-panel">
    <h1>Home Page</h1>
    <p class="admin-muted">Home Page is a special section. Select published portfolio artworks and set their order. Site and branding assets are excluded.</p>
    <form method="post" action="/admin/portfolio-sections/home-page">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <table class="admin-table">
            <thead><tr><th>Show</th><th>Artwork</th><th>Order</th></tr></thead>
            <tbody>{$rows}</tbody>
        </table>
        <p><button type="submit">Save Home Page artworks</button></p>
    </form>
</section>
HTML;

        return Response::html(AdminLayout::render(
            title: 'Home Page Artworks | Admin',
            body: $body,
            nav: [
                '/admin' => 'Dashboard',
                '/admin/artworks' => 'Artworks',
                '/admin/portfolio-sections' => 'Portfolio Sections',
            ],
        ));
    }

    public function update(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser, $tenant)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }

        $submittedIds = $_POST['artwork_ids'] ?? [];
        if (!is_array($submittedIds)) {
            $submittedIds = [];
        }
        $ids = [];
        foreach ($submittedIds as $value) {
            if (is_scalar($value) && ctype_digit((string) $value)) {
                $ids[] = (int) $value;
            }
        }
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        $orders = is_array($_POST['sort_order'] ?? null) ? $_POST['sort_order'] : [];

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                'DELETE FROM homepage_artwork_assignments WHERE tenant_id = :tenant_id'
            )->execute(['tenant_id' => $tenant->tenantId]);

            if ($ids !== []) {
                $tokens = [];
                $parameters = ['tenant_id' => $tenant->tenantId];
                foreach ($ids as $index => $id) {
                    $name = 'artwork_' . $index;
                    $tokens[] = ':' . $name;
                    $parameters[$name] = $id;
                }

                $valid = $this->pdo->prepare(
                    'SELECT a.id
                     FROM artworks a
                     WHERE a.tenant_id = :tenant_id
                       AND a.id IN (' . implode(', ', $tokens) . ')
                       AND NOT EXISTS (
                            SELECT 1
                            FROM artwork_type_assignments ata
                            JOIN artwork_types at ON at.id = ata.type_id
                            WHERE ata.artwork_id = a.id
                              AND at.code = "site"
                       )'
                );
                $valid->execute($parameters);
                $validIds = array_map('intval', $valid->fetchAll(PDO::FETCH_COLUMN));
                if (count($validIds) !== count($ids)) {
                    throw new \RuntimeException('One or more selected records are not portfolio artworks for this tenant.');
                }

                $insert = $this->pdo->prepare(
                    'INSERT INTO homepage_artwork_assignments (
                        tenant_id, artwork_id, sort_order, created_at, updated_at
                     ) VALUES (
                        :tenant_id, :artwork_id, :sort_order, UTC_TIMESTAMP(), UTC_TIMESTAMP()
                     )'
                );

                foreach ($validIds as $position => $artworkId) {
                    $submittedOrder = $orders[(string) $artworkId] ?? null;
                    $sortOrder = is_scalar($submittedOrder) && is_numeric((string) $submittedOrder)
                        ? max(0, (int) $submittedOrder)
                        : (($position + 1) * 10);
                    $insert->execute([
                        'tenant_id' => $tenant->tenantId,
                        'artwork_id' => $artworkId,
                        'sort_order' => $sortOrder,
                    ]);
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return new Response('', 303, [
            'Location' => '/admin/portfolio-sections/home-page?notice=saved',
        ]);
    }

    private function canManage(?array $currentUser, TenantContext $tenant): bool
    {
        return $this->roles->allows(
            $currentUser,
            $tenant,
            ['tenant_owner', 'tenant_admin', 'owner', 'admin'],
        );
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
