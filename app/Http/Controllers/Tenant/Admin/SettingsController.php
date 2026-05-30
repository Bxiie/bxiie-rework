<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;


use App\Http\View\ErrorPage;
use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Membership\Roles;
use App\Platform\Tenancy\TenantContext;
use App\Support\Flash\FlashMessages;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Settings\TenantSettingsRepository;
use PDO;

/**
 * Handles tenant-admin editable client settings.
 */
final class SettingsController
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

        $notice = match ((string) ($_GET['notice'] ?? '')) {
            'saved' => '<p class="admin-notice admin-notice-success">Site settings saved.</p>',
            default => '',
        };

        $csrf = htmlspecialchars($this->csrf->getOrCreate(), ENT_QUOTES, 'UTF-8');
        $siteTitle = $this->setting($tenant, 'site_title', $tenant->name);
        $artistName = $this->setting($tenant, 'artist_name', $tenant->name);
        $browserTitle = $this->setting($tenant, 'browser_title', $siteTitle);
        $siteAdminEmail = $this->setting($tenant, 'site_admin_email', '');
        $copyrightName = $this->setting($tenant, 'copyright_name', $artistName);
        $homeIntro = $this->setting($tenant, 'home_intro', 'Contemporary mixed-media work, archival textures, fragments, signals, and beautiful static from the machine room of memory.');
        $homeTab = $this->setting($tenant, 'home_tab', 'Home');
        $portfolioTab = $this->setting($tenant, 'portfolio_tab', 'Portfolio');
        $aboutTab = $this->setting($tenant, 'about_tab', 'About');
        $contactTab = $this->setting($tenant, 'contact_tab', 'Contact');
        $portfolioSlug = $this->setting($tenant, 'portfolio_slug', 'portfolio');
        $aboutSlug = $this->setting($tenant, 'about_slug', 'about');
        $contactSlug = $this->setting($tenant, 'contact_slug', 'contact');
        $primaryColor = $this->setting($tenant, 'primary_color', '#111111');
        $accentColor = $this->setting($tenant, 'accent_color', '#c9a85f');
        $backgroundColor = $this->setting($tenant, 'background_color', '#f7f2e8');
        $topbarBackgroundColor = $this->setting($tenant, 'topbar_background_color', '');
        $exhibitionsHeading = $this->setting($tenant, 'exhibitions_heading', 'Recent exhibitions');
        $exhibitionsDisplayMode = $this->settings->get($tenant, 'exhibitions_display_mode', 'text');
        $backgroundMode = $this->settings->get($tenant, 'background_mode', 'single');
        $backgroundTileSize = $this->setting($tenant, 'background_tile_size', '360px');
        $backgroundOpacity = $this->setting($tenant, 'background_opacity', '0.12');
        $backgroundMediaUuid = (string) $this->settings->get($tenant, 'background_media_uuid', '');
        $backgroundOptions = $this->backgroundMediaOptions($tenant, $backgroundMediaUuid);
        $tenantCss = htmlspecialchars($this->settings->get($tenant, 'tenant_css',
            'artwork_display_order',
            'recaptcha_site_key',
            'recaptcha_secret_key', ''), ENT_QUOTES, 'UTF-8');
        $artworkDisplayOrder = $this->settings->get($tenant, 'artwork_display_order', 'date_desc');
        $recaptchaSiteKey = $this->setting($tenant, 'recaptcha_site_key', '');
        $recaptchaSecretKey = $this->setting($tenant, 'recaptcha_secret_key', '');

        $selected = fn (string $actual, string $expected): string => $actual === $expected ? ' selected' : '';

        $body = <<<HTML
<main class="admin-shell">
    <p><a href="/admin">&larr; Admin</a></p>
    <h1>Site settings</h1>
    {$notice}
    <form method="post" action="/admin/settings" class="admin-form">
        <input type="hidden" name="csrf_token" value="{$csrf}">

        <fieldset>
            <legend>Identity</legend>
            <label>Site title / visible brand<input name="site_title" value="{$siteTitle}" required></label>
            <label>Artist name<input name="artist_name" value="{$artistName}"></label>
            <label>Browser tab title<input name="browser_title" value="{$browserTitle}"></label>
            <label>Copyright name<input name="copyright_name" value="{$copyrightName}"></label>
            <label>Site admin notification email<input type="email" name="site_admin_email" value="{$siteAdminEmail}"></label>
        </fieldset>

        <fieldset>
            <legend>Home page</legend>
            <label>Home intro text<textarea name="home_intro" rows="5">{$homeIntro}</textarea></label>
        </fieldset>

        <fieldset>
            <legend>Navigation</legend>
            <div class="admin-grid-2">
                <label>Home tab name<input name="home_tab" value="{$homeTab}"></label>
                <label>Portfolio tab name<input name="portfolio_tab" value="{$portfolioTab}"></label>
                <label>About tab name<input name="about_tab" value="{$aboutTab}"></label>
                <label>Contact tab name<input name="contact_tab" value="{$contactTab}"></label>
                <label>Portfolio slug<input name="portfolio_slug" value="{$portfolioSlug}"></label>
                <label>About slug<input name="about_slug" value="{$aboutSlug}"></label>
                <label>Contact slug<input name="contact_slug" value="{$contactSlug}"></label>
            </div>
        </fieldset>

        <fieldset>
            <legend>Colors and background</legend>
            <div class="admin-grid-2">
                <label>Primary color<input name="primary_color" value="{$primaryColor}"></label>
                <label>Accent color<input name="accent_color" value="{$accentColor}"></label>
                <label>Page background color<input name="background_color" value="{$backgroundColor}"></label>
                <label>Top bar background color<input name="topbar_background_color" value="{$topbarBackgroundColor}"></label>
                <label>Background image
                    <select name="background_media_uuid">
                        {$backgroundOptions}
                    </select>
                </label>
                <label>Background mode
                    <select name="background_mode">
                        <option value="single"{$selected($backgroundMode, 'single')}>Single image</option>
                        <option value="tile"{$selected($backgroundMode, 'tile')}>Tile</option>
                    </select>
                </label>
                <label>Background tile size<input name="background_tile_size" value="{$backgroundTileSize}"></label>
                <label>Background opacity<input name="background_opacity" value="{$backgroundOpacity}"></label>
            </div>
            <p class="admin-help">Background images are selected from published artwork so the public media route can serve them safely. Use opacity between 0 and 1; tile size accepts CSS values like 240px or 18rem.</p>
        </fieldset>

        <fieldset>
            <legend>Artwork display</legend>
            <label>Default artwork order
                <select name="artwork_display_order">
                    <option value="name"{$selected($artworkDisplayOrder, 'name')}>Name</option>
                    <option value="date"{$selected($artworkDisplayOrder, 'date')}>Date ascending</option>
                    <option value="date_desc"{$selected($artworkDisplayOrder, 'date_desc')}>Date descending</option>
                    <option value="medium"{$selected($artworkDisplayOrder, 'medium')}>Medium</option>
                    <option value="manual"{$selected($artworkDisplayOrder, 'manual')}>Manual ordering</option>
                </select>
            </label>
        </fieldset>

        <fieldset>
            <legend>Spam protection</legend>
            <div class="admin-grid-2">
                <label>reCAPTCHA site key<input name="recaptcha_site_key" value="{$recaptchaSiteKey}"></label>
                <label>reCAPTCHA secret key<input type="password" name="recaptcha_secret_key" value="{$recaptchaSecretKey}"></label>
            </div>
            <p class="admin-help">Blank values inherit platform reCAPTCHA settings.</p>
        </fieldset>

        <fieldset>
            <legend>Exhibitions</legend>
            <label>Exhibitions heading<input name="exhibitions_heading" value="{$exhibitionsHeading}"></label>
            <label>Exhibitions display mode
                <select name="exhibitions_display_mode">
                    <option value="text"{$selected($exhibitionsDisplayMode, 'text')}>Text cards</option>
                    <option value="table"{$selected($exhibitionsDisplayMode, 'table')}>Table</option>
                </select>
            </label>
        </fieldset>

        <fieldset>
            <legend>Tenant CSS</legend>
            <label>Custom CSS<textarea name="tenant_css" rows="18" spellcheck="false">{$tenantCss}</textarea></label>
            <p class="admin-help">This CSS is loaded after the default public stylesheet.</p>
        </fieldset>

        <button type="submit">Save site settings</button>
    </form>
</main>
HTML;

        return Response::html(AdminLayout::render('Settings', $body));
    }

    public function update(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::html('<h1>Invalid request</h1><p>Security check failed.</p>', 419);
        }

        $keys = [
            'site_title',
            'artist_name',
            'browser_title',
            'copyright_name',
            'site_admin_email',
            'home_intro',
            'home_tab',
            'portfolio_tab',
            'about_tab',
            'contact_tab',
            'portfolio_slug',
            'about_slug',
            'contact_slug',
            'primary_color',
            'accent_color',
            'background_color',
            'topbar_background_color',
            'background_media_uuid',
            'background_mode',
            'background_tile_size',
            'background_opacity',
            'exhibitions_heading',
            'exhibitions_display_mode',
            'tenant_css',
            'artwork_display_order',
            'recaptcha_site_key',
            'recaptcha_secret_key',
        ];

        $before = [];
        $after = [];

        foreach ($keys as $key) {
            $before[$key] = $this->settings->get($tenant, $key, '');
            $value = trim((string) ($_POST[$key] ?? ''));
            if ($key === 'background_media_uuid') {
                $value = $this->safeBackgroundMediaUuid($tenant, $value);
            }
            if (str_ends_with($key, '_slug')) {
                $value = $this->safeSlug($value, str_replace('_slug', '', $key));
            }
            $this->settings->set($tenant, $key, $value);
            $after[$key] = $value;
        }

        $this->auditAction($request, $tenant, $currentUser, [
            'before' => $before,
            'after' => $after,
        ]);

        return new Response('', 303, ['Location' => '/admin/settings?notice=saved']);
    }


    /**
     * Builds a tenant-scoped picker of published artwork media for public-safe backgrounds.
     */
    private function backgroundMediaOptions(TenantContext $tenant, string $selectedUuid): string
    {
        $options = '<option value="">None</option>';

        if ($this->pdo === null) {
            return $options;
        }

        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT
                m.uuid,
                COALESCE(NULLIF(m.title, ''), NULLIF(a.title, ''), m.original_filename) AS label,
                a.year_created
             FROM media_assets m
             INNER JOIN artworks a
                ON a.primary_media_id = m.id
               AND a.tenant_id = m.tenant_id
               AND a.status = 'published'
             WHERE m.tenant_id = :tenant_id
               AND m.is_private = 0
               AND (m.mime_type LIKE 'image/%' OR m.mime_type IS NULL)
             ORDER BY label ASC
             LIMIT 300"
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId]);

        foreach ($stmt->fetchAll() as $row) {
            $uuid = (string) $row['uuid'];
            $label = (string) $row['label'];
            if (!empty($row['year_created'])) {
                $label .= ' · ' . (string) $row['year_created'];
            }

            $safeUuid = htmlspecialchars($uuid, ENT_QUOTES, 'UTF-8');
            $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
            $selected = $uuid === $selectedUuid ? ' selected' : '';
            $options .= "<option value=\"{$safeUuid}\"{$selected}>{$safeLabel}</option>";
        }

        return $options;
    }

    /**
     * Normalizes background media IDs so arbitrary form values cannot be persisted.
     */
    private function safeBackgroundMediaUuid(TenantContext $tenant, string $value): string
    {
        $value = strtolower(trim($value));

        if ($value === '') {
            return '';
        }

        if (!preg_match('/^[a-f0-9-]{36}$/', $value) || $this->pdo === null) {
            return '';
        }

        $stmt = $this->pdo->prepare(
            "SELECT m.uuid
             FROM media_assets m
             INNER JOIN artworks a
                ON a.primary_media_id = m.id
               AND a.tenant_id = m.tenant_id
               AND a.status = 'published'
             WHERE m.tenant_id = :tenant_id
               AND m.uuid = :media_uuid
               AND m.is_private = 0
               AND (m.mime_type LIKE 'image/%' OR m.mime_type IS NULL)
             LIMIT 1"
        );
        $stmt->execute([
            'tenant_id' => $tenant->tenantId,
            'media_uuid' => $value,
        ]);

        return $stmt->fetch() ? $value : '';
    }

private function backgroundPreview(string $mediaUuid): string
    {
        if ($mediaUuid === '') {
            return '<p class="admin-help">No background image is currently selected.</p>';
        }

        $src = '/media?uuid=' . rawurlencode($mediaUuid);

        return '<div class="admin-media-preview"><strong>Current background image</strong><br><img src="' . $this->escape($src) . '" alt="Selected background image preview" style="max-width:260px;max-height:160px;object-fit:contain;background:#fff;border:1px solid #ddd;padding:.5rem;"></div>';
    }

    private function settingsPdo(): \PDO
    {
        $reflection = new \ReflectionObject($this->settings);
        $property = $reflection->getProperty('pdo');
        $property->setAccessible(true);

        return $property->getValue($this->settings);
    }

    private function safeSlug(string $value, string $default): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : $default;
    }


    private function setting(TenantContext $tenant, string $key, string $default = ''): string
    {
        return htmlspecialchars($this->settings->get($tenant, $key, $default), ENT_QUOTES, 'UTF-8');
    }

    private function auditAction(
        Request $request,
        TenantContext $tenant,
        ?array $currentUser,
        array $details = [],
    ): void {
        if (!$this->auditLog) {
            return;
        }

        $this->auditLog->record('tenant.settings.updated', $tenant->tenantId, isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null, 'tenant_settings', (string) $tenant->tenantId, $details, $request->server('REMOTE_ADDR'));
    }

    private function canManageSettings(?array $currentUser, TenantContext $tenant): bool
    {
        return $this->roles->allows(
            currentUser: $currentUser,
            tenant: $tenant,
            allowedRoles: [Roles::TENANT_OWNER, Roles::TENANT_ADMIN],
        );
    }

    private function escape(string $value): string
    {
        return AdminLayout::escape($value);
    }
    private function recordAudit(Request $request, TenantContext $tenant, ?array $currentUser, string $action, array $details): void
    {
        $userId = isset($currentUser['id']) ? (int) $currentUser['id'] : null;

        $this->auditLog->record(
            $action,
            $tenant->tenantId,
            $userId,
            'tenant_settings',
            (string) $tenant->tenantId,
            $details,
            $request->ip(),
        );
    }

}

// End of file.
