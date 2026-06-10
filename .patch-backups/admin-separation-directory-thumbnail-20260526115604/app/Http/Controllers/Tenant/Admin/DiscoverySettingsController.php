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
use Throwable;

/**
 * Lets tenant admins opt into the public ArtsFolio directory and choose the
 * artwork image used as the directory card thumbnail.
 */
final class DiscoverySettingsController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly TenantSettingsRepository $settings,
        private readonly CsrfTokenService $csrf,
        private readonly ?AuditLogRepository $auditLog = null,
        private readonly ?PDO $pdo = null,
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
        $artworkOptions = $this->directoryThumbnailOptions($tenant, $selectedArtworkId);
        $notice = isset($_GET['notice']) ? '<p class="notice">Directory settings saved. The public directory now reads opt-in, summary, and thumbnail artwork from tenant settings.</p>' : '';

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
        <label>Artwork image shown on the ArtsFolio directory card
            <select name="platform_directory_thumbnail_artwork_id">
                {$artworkOptions}
            </select>
        </label>
        <p class="admin-muted">Choose a published artwork with an uploaded primary image. The public directory will use that artwork's image as the tenant thumbnail.</p>
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
        $thumbnailArtworkId = $this->validDirectoryThumbnailArtworkId($tenant, (int) ($_POST['platform_directory_thumbnail_artwork_id'] ?? 0));

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

    /**
     * Builds a safe select list from published artworks that have primary media.
     */
    private function directoryThumbnailOptions(TenantContext $tenant, int $selectedArtworkId): string
    {
        $options = '<option value="0">No thumbnail selected</option>';

        if (!$this->pdo) {
            return $options;
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT a.id, a.title, a.year_created, m.uuid AS media_uuid
                 FROM artworks a
                 INNER JOIN media_assets m ON m.id = a.primary_media_id
                 WHERE a.tenant_id = :tenant_id
                   AND a.status = 'published'
                   AND a.primary_media_id IS NOT NULL
                 ORDER BY a.sort_order ASC, a.title ASC, a.id ASC
                 LIMIT 500"
            );
            $stmt->execute(['tenant_id' => $tenant->tenantId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return $options . '<option value="0" disabled>Artwork list unavailable</option>';
        }

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $selected = $id === $selectedArtworkId ? ' selected' : '';
            $year = trim((string) ($row['year_created'] ?? ''));
            $label = (string) ($row['title'] ?? 'Untitled artwork');
            if ($year !== '') {
                $label .= ' (' . $year . ')';
            }
            $options .= '<option value="' . $id . '"' . $selected . '>' . $this->escape($label) . '</option>';
        }

        if (count($rows) === 0) {
            $options .= '<option value="0" disabled>No published artwork with a primary image yet</option>';
        }

        return $options;
    }

    /**
     * Rejects stale, cross-tenant, draft, archived, or imageless artwork IDs.
     */
    private function validDirectoryThumbnailArtworkId(TenantContext $tenant, int $artworkId): int
    {
        if ($artworkId <= 0 || !$this->pdo) {
            return 0;
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT a.id
                 FROM artworks a
                 INNER JOIN media_assets m ON m.id = a.primary_media_id
                 WHERE a.id = :artwork_id
                   AND a.tenant_id = :tenant_id
                   AND a.status = 'published'
                   AND a.primary_media_id IS NOT NULL
                 LIMIT 1"
            );
            $stmt->execute([
                'artwork_id' => $artworkId,
                'tenant_id' => $tenant->tenantId,
            ]);

            return $stmt->fetchColumn() ? $artworkId : 0;
        } catch (Throwable) {
            return 0;
        }
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
