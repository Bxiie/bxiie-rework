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
 * Lets tenant admins opt the tenant into the public ArtsFolio directory and
 * choose the artwork thumbnail used by the platform directory card.
 */
final class DiscoverySettingsController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly TenantSettingsRepository $settings,
        private readonly CsrfTokenService $csrf,
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
        $notice = isset($_GET['notice']) ? '<p class="notice">Directory settings saved. The public directory will use the selected published artwork thumbnail.</p>' : '';
        $artworkOptions = $this->artworkOptions($tenant, $selectedArtworkId);
        $preview = $this->thumbnailPreview($tenant, $selectedArtworkId);

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
        <legend>Directory thumbnail artwork</legend>
        <p class="admin-muted">Select a published artwork with a primary image. This thumbnail appears on the public ArtsFolio directory card.</p>
        <label>Directory thumbnail
            <select name="platform_directory_thumbnail_artwork_id">
                <option value="0">Use automatic fallback</option>
                {$artworkOptions}
            </select>
        </label>
        {$preview}
        <p><a href="/admin/artworks">Manage artworks</a></p>
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
        $thumbnailArtworkId = $this->validArtworkId($tenant, (int) ($_POST['platform_directory_thumbnail_artwork_id'] ?? 0));

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
                    'platform_directory_thumbnail_artwork_id' => $thumbnailArtworkId,
                ],
                $request->server('REMOTE_ADDR')
            );
        }

        return new Response('', 303, ['Location' => '/admin/directory?notice=saved']);
    }

    private function artworkOptions(TenantContext $tenant, int $selectedArtworkId): string
    {
        $pdo = $this->pdo();
        $stmt = $pdo->prepare(
            "SELECT a.id, a.title, m.uuid AS media_uuid
             FROM artworks a
             JOIN media_assets m ON m.id = a.primary_media_id AND m.tenant_id = a.tenant_id
             WHERE a.tenant_id = :tenant_id
               AND a.status = 'published'
               AND a.primary_media_id IS NOT NULL
             ORDER BY a.title ASC, a.id DESC
             LIMIT 500"
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId]);

        $html = '';
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int) $row['id'];
            $selected = $id === $selectedArtworkId ? ' selected' : '';
            $label = ((string) $row['title']) !== '' ? (string) $row['title'] : 'Artwork #' . $id;
            $html .= '<option value="' . $id . '"' . $selected . '>' . $this->escape($label) . '</option>';
        }

        if ($html === '') {
            return '<option value="0">No published artworks with primary images are available</option>';
        }

        return $html;
    }

    private function thumbnailPreview(TenantContext $tenant, int $selectedArtworkId): string
    {
        if ($selectedArtworkId <= 0) {
            return '<p class="admin-muted">No artwork selected. The public directory will use its automatic fallback.</p>';
        }

        $pdo = $this->pdo();
        $stmt = $pdo->prepare(
            "SELECT a.title, m.uuid AS media_uuid
             FROM artworks a
             JOIN media_assets m ON m.id = a.primary_media_id AND m.tenant_id = a.tenant_id
             WHERE a.tenant_id = :tenant_id
               AND a.id = :artwork_id
               AND a.status = 'published'
             LIMIT 1"
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'artwork_id' => $selectedArtworkId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return '<p class="admin-error">The previously selected artwork is no longer available. Choose a published artwork with a primary image.</p>';
        }

        $title = $this->escape((string) $row['title']);
        $uuid = rawurlencode((string) $row['media_uuid']);

        return <<<HTML
<div class="directory-thumbnail-preview">
    <img src="/media?uuid={$uuid}" alt="">
    <div><strong>Current directory thumbnail</strong><p>{$title}</p></div>
</div>
HTML;
    }

    private function validArtworkId(TenantContext $tenant, int $artworkId): int
    {
        if ($artworkId <= 0) {
            return 0;
        }

        $stmt = $this->pdo()->prepare(
            "SELECT a.id
             FROM artworks a
             JOIN media_assets m ON m.id = a.primary_media_id AND m.tenant_id = a.tenant_id
             WHERE a.tenant_id = :tenant_id
               AND a.id = :artwork_id
               AND a.status = 'published'
             LIMIT 1"
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'artwork_id' => $artworkId]);

        return $stmt->fetchColumn() ? $artworkId : 0;
    }

    private function pdo(): PDO
    {
        $property = new \ReflectionProperty(TenantSettingsRepository::class, 'pdo');
        $property->setAccessible(true);
        /** @var PDO $pdo */
        $pdo = $property->getValue($this->settings);

        return $pdo;
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
