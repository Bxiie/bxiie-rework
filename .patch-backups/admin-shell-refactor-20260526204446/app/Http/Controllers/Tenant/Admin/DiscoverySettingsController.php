<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\ErrorPage;
use App\Http\View\TenantAdminLayout;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Settings\TenantSettingsRepository;
use PDO;

/**
 * Lets tenant admins control the public ArtsFolio directory listing.
 */
final class DiscoverySettingsController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly TenantSettingsRepository $settings,
        private readonly CsrfTokenService $csrf,
        private readonly PDO $pdo,
        private readonly ?AuditLogRepository $auditLog = null,
    ) {
    }

    public function edit(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        $layout = new TenantAdminLayout($this->settings);
        $token = $this->escape($this->csrf->getOrCreate());
        $checked = $this->truthy($this->settings->get($tenant, 'platform_directory_opt_in', '0') ?? '0') ? ' checked' : '';
        $summary = $this->escape($this->settings->get($tenant, 'platform_directory_summary', '') ?? '');
        $selectedArtworkId = (int) ($this->settings->get($tenant, 'platform_directory_thumbnail_artwork_id', '0') ?? '0');
        $notice = isset($_GET['notice']) ? '<p class="notice">Directory settings saved.</p>' : '';
        $thumbnailChoices = $this->thumbnailChoicesHtml($tenant, $selectedArtworkId);

        $body = <<<HTML
{$notice}
<p class="admin-muted">Control whether this artist appears in the public ArtsFolio directory on artsfol.io. The platform-wide directory switch must also be enabled by a platform admin.</p>

<form method="post" action="/admin/directory" class="admin-form">
    <input type="hidden" name="csrf_token" value="{$token}">

    <fieldset>
        <legend>Directory listing</legend>
        <label class="checkbox-row">
            <span><input type="checkbox" name="platform_directory_opt_in" value="1"{$checked}> Show this tenant in the public ArtsFolio directory</span>
        </label>
        <p class="admin-muted">Leave this off for private portfolios, sites still in setup, or artists who do not want platform-level discovery.</p>
    </fieldset>

    <fieldset>
        <legend>Directory thumbnail</legend>
        <p class="admin-muted">Choose one published artwork image to represent this artist in the public directory. If nothing is selected, the directory card uses a text-only fallback.</p>
        <div class="directory-thumbnail-picker">
            {$thumbnailChoices}
        </div>
    </fieldset>

    <fieldset>
        <legend>Directory summary</legend>
        <label>Short public description
            <textarea name="platform_directory_summary" rows="5" maxlength="500">{$summary}</textarea>
        </label>
        <p class="admin-muted">This appears on directory cards. Keep it plain, specific, and collector-readable.</p>
    </fieldset>

    <p><button type="submit">Save directory settings</button></p>
</form>
HTML;

        return Response::html($layout->render($tenant, 'Directory', $body, 'directory'));
    }

    public function update(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::html('<h1>Invalid request</h1>', 419);
        }

        $optIn = isset($_POST['platform_directory_opt_in']) ? '1' : '0';
        $summary = trim((string) ($_POST['platform_directory_summary'] ?? ''));
        $thumbnailArtworkId = (int) ($_POST['platform_directory_thumbnail_artwork_id'] ?? 0);

        if ($thumbnailArtworkId > 0 && !$this->thumbnailArtworkExists($tenant, $thumbnailArtworkId)) {
            return Response::html('<h1>Invalid thumbnail</h1><p>Select a published artwork that belongs to this tenant.</p>', 422);
        }

        $this->settings->set($tenant, 'platform_directory_opt_in', $optIn);
        $this->settings->set($tenant, 'platform_directory_summary', $summary);
        $this->settings->set($tenant, 'platform_directory_thumbnail_artwork_id', $thumbnailArtworkId > 0 ? (string) $thumbnailArtworkId : '');

        if ($this->auditLog) {
            $this->auditLog->record(
                'tenant.directory_settings.updated',
                $tenant->tenantId,
                isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null,
                'tenant_settings',
                (string) $tenant->tenantId,
                [
                    'platform_directory_opt_in' => $optIn,
                    'platform_directory_thumbnail_artwork_id' => $thumbnailArtworkId > 0 ? $thumbnailArtworkId : null,
                ],
                $request->server('REMOTE_ADDR')
            );
        }

        return new Response('', 303, ['Location' => '/admin/directory?notice=saved']);
    }

    private function thumbnailChoicesHtml(TenantContext $tenant, int $selectedArtworkId): string
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.id, a.title, a.year_created, m.uuid AS media_uuid
             FROM artworks a
             INNER JOIN media_assets m ON m.id = a.primary_media_id
             WHERE a.tenant_id = :tenant_id
               AND a.status = 'published'
               AND m.is_private = 0
             ORDER BY a.sort_order ASC, a.title ASC, a.id DESC
             LIMIT 200"
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId]);
        $rows = $stmt->fetchAll();

        $noneChecked = $selectedArtworkId === 0 ? ' checked' : '';
        $html = <<<HTML
<label class="directory-thumbnail-option directory-thumbnail-option-none">
    <input type="radio" name="platform_directory_thumbnail_artwork_id" value="0"{$noneChecked}>
    <span>No thumbnail</span>
</label>
HTML;

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $checked = $id === $selectedArtworkId ? ' checked' : '';
            $title = $this->escape((string) $row['title']);
            $year = $this->escape((string) ($row['year_created'] ?? ''));
            $src = '/admin/media?uuid=' . rawurlencode((string) $row['media_uuid']);
            $src = $this->escape($src);
            $meta = $year !== '' ? '<small>' . $year . '</small>' : '<small>Published artwork</small>';
            $html .= <<<HTML
<label class="directory-thumbnail-option">
    <input type="radio" name="platform_directory_thumbnail_artwork_id" value="{$id}"{$checked}>
    <img src="{$src}" alt="{$title}">
    <span><strong>{$title}</strong>{$meta}</span>
</label>
HTML;
        }

        if (count($rows) === 0) {
            $html .= '<p class="admin-muted">No eligible thumbnail images yet. Publish an artwork with a primary image, then return here.</p>';
        }

        return $html;
    }

    private function thumbnailArtworkExists(TenantContext $tenant, int $artworkId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.id
             FROM artworks a
             INNER JOIN media_assets m ON m.id = a.primary_media_id
             WHERE a.tenant_id = :tenant_id
               AND a.id = :artwork_id
               AND a.status = 'published'
               AND m.is_private = 0
             LIMIT 1"
        );
        $stmt->execute([
            'tenant_id' => $tenant->tenantId,
            'artwork_id' => $artworkId,
        ]);

        return (bool) $stmt->fetch();
    }

    private function truthy(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
