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
use App\Tenant\Settings\TenantSettingsRepository;
use PDO;

/** Manages the virtual Home Page portfolio section. */
final class HomepageArtworksController
{
    // HOME_PAGE_ALLOWS_DRAFT_ARTWORK: public visibility follows normal portfolio status behavior.
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly PDO $pdo,
        private readonly CsrfTokenService $csrf,
        private readonly TenantSettingsRepository $settings,
    ) {
    }

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser, $tenant)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        $statement = $this->pdo->prepare(
            'SELECT a.id, a.title, a.slug, a.status, a.primary_media_id,
                    CASE WHEN h.id IS NULL THEN 0 ELSE 1 END AS selected,
                    COALESCE(h.sort_order, a.sort_order, 0) AS home_sort_order
             FROM artworks a
             LEFT JOIN homepage_artwork_assignments h
               ON h.tenant_id = a.tenant_id
              AND h.artwork_id = a.id
             WHERE a.tenant_id = :tenant_id
               AND a.status <> "archived"
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

        $heroArtworkId = max(0, (int) $this->settings->get($tenant, 'home_hero_artwork_id', ''));
        $rows = '';
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $artwork) {
            $id = (int) $artwork['id'];
            $checked = (int) $artwork['selected'] === 1 ? ' checked' : '';
            $title = $this->e((string) $artwork['title']);
            $status = $this->e((string) $artwork['status']);
            $order = max(0, (int) $artwork['home_sort_order']);
            $disabled = $artwork['status'] === 'published' ? '' : ' disabled';
            $hasImage = !empty($artwork['primary_media_id']);
            $heroChecked = $heroArtworkId === $id ? ' checked' : '';
            $heroDisabled = $hasImage ? $disabled : ' disabled';
            $heroHelp = $hasImage ? '' : '<br><small class="admin-muted">No image</small>';

            $rows .= '<tr>'
                . '<td colspan="2"><label><input type="checkbox" name="artwork_ids[]" value="' . $id . '"' . $checked . $disabled . '>'
                . '<span><strong>' . $title . '</strong><br><span class="admin-muted">' . $status . '</span></span></label></td>'
                . '<td><input type="number" min="0" step="10" name="sort_order[' . $id . ']" value="' . $order . '" style="width:7rem"' . $disabled . '></td>'
                . '<td><label><input type="radio" name="hero_artwork_id" value="' . $id . '"' . $heroChecked . $heroDisabled . '> Hero</label>' . $heroHelp . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="4">No portfolio artworks are available.</td></tr>';
        }

        $notice = (string) ($_GET['notice'] ?? '') === 'saved'
            ? '<p class="admin-notice admin-notice-success">Home Page artwork selection saved.</p>'
            : '';
        $csrf = $this->e($this->csrf->getOrCreate());
        $noHeroChecked = $heroArtworkId === 0 ? ' checked' : '';

        $body = <<<HTML
{$notice}
<p><a href="/admin/portfolio-sections">← Back to Portfolio Sections</a></p>
<section class="admin-panel">
    <h1>Home Page</h1>
    <p class="admin-muted">Home Page is a special section. Select published portfolio artworks and set their order. Site and branding assets are excluded.</p>
    <form method="post" action="/admin/portfolio-sections/home-page">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <p class="admin-help">Optionally feature one shown artwork as a single large hero image at the top of the home page, above the grid of the rest.</p>
        <p><label><input type="radio" name="hero_artwork_id" value="0"{$noHeroChecked}> No hero image — show the plain grid</label></p>
        <table class="admin-table">
            <thead><tr><th>Show</th><th>Artwork</th><th>Order</th><th>Hero image</th></tr></thead>
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
               AND a.status <> "archived"
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

        // A hero image must be one of the artworks just saved into the Home
        // Page selection, with a primary image to actually display. Any other
        // submitted value (removed from selection, imageless, tampered) clears
        // the hero rather than erroring, since it can only leave the home page
        // showing its plain grid.
        $requestedHeroId = max(0, (int) ($_POST['hero_artwork_id'] ?? 0));
        $heroArtworkId = $requestedHeroId > 0
            && in_array($requestedHeroId, $ids, true)
            && $this->artworkHasPrimaryImage($tenant, $requestedHeroId)
            ? $requestedHeroId
            : 0;
        $this->settings->set($tenant, 'home_hero_artwork_id', $heroArtworkId > 0 ? (string) $heroArtworkId : '');

        return new Response('', 303, [
            'Location' => '/admin/portfolio-sections/home-page?notice=saved',
        ]);
    }

    private function artworkHasPrimaryImage(TenantContext $tenant, int $artworkId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM artworks
             WHERE id = :artwork_id
               AND tenant_id = :tenant_id
               AND primary_media_id IS NOT NULL
             LIMIT 1'
        );
        $stmt->execute(['artwork_id' => $artworkId, 'tenant_id' => $tenant->tenantId]);

        return (bool) $stmt->fetchColumn();
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
