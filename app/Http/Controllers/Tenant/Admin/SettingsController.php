<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Http\View\ErrorPage;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Tenancy\TenantContext;
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

        $notice = ((string) ($_GET['notice'] ?? '')) === 'saved'
            ? '<p class="admin-notice admin-notice-success">Site settings saved.</p>'
            : '';

        $csrf = $this->escape($this->csrf->getOrCreate());
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
        $topbarBackgroundOpacity = $this->setting($tenant, 'topbar_background_opacity', '1');
        $topbarBackgroundMediaUuid = (string) $this->settings->get($tenant, 'topbar_background_media_uuid', '');
        $topbarBackgroundPicker = $this->siteImagePicker($tenant, 'topbar_background_media_uuid', $topbarBackgroundMediaUuid, true);
        $exhibitionsHeading = $this->setting($tenant, 'exhibitions_heading', 'Recent exhibitions');
        $exhibitionsDisplayMode = (string) $this->settings->get($tenant, 'exhibitions_display_mode', 'text');
        $backgroundMode = (string) $this->settings->get($tenant, 'background_mode', 'single');
        $backgroundTileSize = $this->setting($tenant, 'background_tile_size', '360px');
        $backgroundOpacity = $this->setting($tenant, 'background_opacity', '0.12');
        $backgroundMediaUuid = (string) $this->settings->get($tenant, 'background_media_uuid', '');
        $backgroundPicker = $this->siteImagePicker($tenant, 'background_media_uuid', $backgroundMediaUuid, true);
        $tenantCss = $this->setting($tenant, 'tenant_css', '');
        $artworkDisplayOrder = (string) $this->settings->get($tenant, 'artwork_display_order', 'date_desc');
        $recaptchaSiteKey = $this->setting($tenant, 'recaptcha_site_key', '');
        $recaptchaSecretKey = $this->setting($tenant, 'recaptcha_secret_key', '');

        $selected = static fn (string $actual, string $expected): string => $actual === $expected ? ' selected' : '';

        $body = <<<HTML
<main class="admin-shell">
    <h1>Site settings</h1>
    {$notice}
    <form method="post" action="/admin/settings" class="admin-form">
        <input type="hidden" name="csrf_token" value="{$csrf}">

        <fieldset>
            <legend>Identity</legend>
            <label>Site title / menu and browser brand<input name="site_title" value="{$siteTitle}" required></label>
            <label>Artist name / public home headline<input name="artist_name" value="{$artistName}"></label>
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
                <label>Top bar background image opacity<input type="number" name="topbar_background_opacity" min="0" max="1" step="0.05" value="{$topbarBackgroundOpacity}"></label>
                <label>Background mode
                    <select name="background_mode">
                        <option value="single"{$selected($backgroundMode, 'single')}>Single image</option>
                        <option value="tile"{$selected($backgroundMode, 'tile')}>Tile</option>
                    </select>
                </label>
                <label>Background tile size<input name="background_tile_size" value="{$backgroundTileSize}"></label>
                <label>Background opacity<input type="number" name="background_opacity" min="0" max="1" step="0.05" value="{$backgroundOpacity}"></label>
            </div>
            <h3>Top bar background image</h3>
            {$topbarBackgroundPicker}
            <h3>Page background image</h3>
            {$backgroundPicker}
            <p class="admin-help">Only published artwork marked as Site Images appears here. Use opacity between 0 and 1; tile size accepts CSS values like 240px or 18rem.</p>
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
            'site_title', 'artist_name', 'browser_title', 'copyright_name', 'site_admin_email', 'home_intro',
            'home_tab', 'portfolio_tab', 'about_tab', 'contact_tab', 'portfolio_slug', 'about_slug', 'contact_slug',
            'primary_color', 'accent_color', 'background_color', 'topbar_background_color', 'topbar_background_media_uuid', 'topbar_background_opacity', 'background_media_uuid',
            'background_mode', 'background_tile_size', 'background_opacity', 'exhibitions_heading', 'exhibitions_display_mode',
            'tenant_css', 'artwork_display_order', 'recaptcha_site_key', 'recaptcha_secret_key',
        ];

        $before = [];
        $after = [];

        foreach ($keys as $key) {
            $before[$key] = $this->settings->get($tenant, $key, '');
            $value = trim((string) ($_POST[$key] ?? ''));
            if ($key === 'background_media_uuid' || $key === 'topbar_background_media_uuid') {
                $value = $this->safeSiteImageMediaUuid($tenant, $value);
            }
            if ($key === 'background_opacity' || $key === 'topbar_background_opacity') {
                $value = $this->safeOpacity($value, '0.12');
            }
            if (str_ends_with($key, '_slug')) {
                $value = $this->safeSlug($value, str_replace('_slug', '', $key));
            }
            $this->settings->set($tenant, $key, $value);
            $after[$key] = $value;
        }

        $this->auditAction($request, $tenant, $currentUser, ['before' => $before, 'after' => $after]);

        return new Response('', 303, ['Location' => '/admin/settings?notice=saved']);
    }

    /**
     * Builds a thumbnail radio picker from published Site Images.
     */
    private function siteImagePicker(TenantContext $tenant, string $fieldName, string $selectedUuid, bool $includeNone): string
    {
        $cards = $includeNone ? '<label class="site-image-picker-card"><input type="radio" name="' . $this->escape($fieldName) . '" value=""' . ($selectedUuid === '' ? ' checked' : '') . '><span>No image</span></label>' : '';

        if ($this->pdo === null) {
            return '<div class="site-image-picker">' . $cards . '</div>';
        }

        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT m.uuid, COALESCE(NULLIF(m.title, ''), NULLIF(a.title, ''), m.original_filename) AS label, a.year_created
             FROM media_assets m
             INNER JOIN artworks a ON a.primary_media_id = m.id AND a.tenant_id = m.tenant_id AND a.status = 'published'
             INNER JOIN artwork_type_assignments ata ON ata.artwork_id = a.id
             INNER JOIN artwork_types atype ON atype.id = ata.type_id AND atype.code = 'site_images'
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
            $safeUuid = $this->escape($uuid);
            $safeLabel = $this->escape($label);
            $checked = $uuid === $selectedUuid ? ' checked' : '';
            $src = '/media?uuid=' . rawurlencode($uuid) . '&variant=thumb';
            $cards .= '<label class="site-image-picker-card"><input type="radio" name="' . $this->escape($fieldName) . '" value="' . $safeUuid . '"' . $checked . '><img src="' . $this->escape($src) . '" alt=""><span>' . $safeLabel . '</span></label>';
        }

        return '<div class="site-image-picker">' . $cards . '</div>';
    }

    /**
     * Normalizes media IDs so arbitrary form values cannot be persisted.
     */
    private function safeSiteImageMediaUuid(TenantContext $tenant, string $value): string
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
             INNER JOIN artworks a ON a.primary_media_id = m.id AND a.tenant_id = m.tenant_id AND a.status = 'published'
             INNER JOIN artwork_type_assignments ata ON ata.artwork_id = a.id
             INNER JOIN artwork_types atype ON atype.id = ata.type_id AND atype.code = 'site_images'
             WHERE m.tenant_id = :tenant_id
               AND m.uuid = :media_uuid
               AND m.is_private = 0
               AND (m.mime_type LIKE 'image/%' OR m.mime_type IS NULL)
             LIMIT 1"
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'media_uuid' => $value]);

        return $stmt->fetch() ? $value : '';
    }

    private function safeOpacity(string $value, string $default): string
    {
        $opacity = is_numeric($value) ? (float) $value : (float) $default;
        $opacity = max(0.0, min(1.0, $opacity));

        return rtrim(rtrim(sprintf('%.2F', $opacity), '0'), '.');
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
        return $this->escape((string) $this->settings->get($tenant, $key, $default));
    }

    private function auditAction(Request $request, TenantContext $tenant, ?array $currentUser, array $details = []): void
    {
        if (!$this->auditLog) {
            return;
        }

        $this->auditLog->record('tenant.settings.updated', $tenant->tenantId, isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null, 'tenant_settings', (string) $tenant->tenantId, $details, $request->server('REMOTE_ADDR'));
    }

    private function escape(string $value): string
    {
        return AdminLayout::escape($value);
    }
}

// End of file.
