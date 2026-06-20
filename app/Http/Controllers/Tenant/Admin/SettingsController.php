<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\TenantAdminLayout;
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
        $activeSection = $this->activeSettingsSection((string) ($_GET['section'] ?? 'identity'));
        $sectionLabels = $this->settingsSections();
        $sectionLabel = $sectionLabels[$activeSection]['label'];

        $csrf = $this->escape($this->csrf->getOrCreate());
        $siteTitle = $this->setting($tenant, 'site_title', $tenant->name);
        $artistName = $this->setting($tenant, 'artist_name', $tenant->name);
        $browserTitle = $this->setting($tenant, 'browser_title', $siteTitle);
        $siteAdminEmail = $this->setting($tenant, 'site_admin_email', '');
        $copyrightName = $this->setting($tenant, 'copyright_name', $artistName);
        $homeIntro = $this->setting($tenant, 'home_intro', 'Contemporary mixed-media work, archival textures, fragments, signals, and beautiful static from the machine room of memory.');
        $salesNotes = $this->setting($tenant, 'sales_notes', 'Sales are handled directly by the artist. Contact the studio for shipping, pickup, installation, and timing details.');
        $stripeConnectedAccountId = $this->setting($tenant, 'stripe_connected_account_id', '');
        $homeTab = $this->setting($tenant, 'home_tab', 'Home');
        $portfolioTab = $this->setting($tenant, 'portfolio_tab', 'Portfolio');
        $aboutTab = $this->setting($tenant, 'about_tab', 'About');
        $contactTab = $this->setting($tenant, 'contact_tab', 'Contact');
        $portfolioSlug = $this->setting($tenant, 'portfolio_slug', 'portfolio');
        $aboutSlug = $this->setting($tenant, 'about_slug', 'about');
        $contactSlug = $this->setting($tenant, 'contact_slug', 'contact');
        $fontFamilies = $this->fontFamilyOptions();
        $bodyFontFamily = $this->setting($tenant, 'font_family_body', 'Inter, ui-sans-serif, system-ui, sans-serif');
        $headingFontFamily = $this->setting($tenant, 'font_family_heading', $bodyFontFamily);
        $brandFontFamily = $this->setting($tenant, 'font_family_brand', $headingFontFamily);
        $navFontFamily = $this->setting($tenant, 'font_family_nav', $bodyFontFamily);
        $artworkTitleFontFamily = $this->setting($tenant, 'font_family_artwork_title', $headingFontFamily);
        $artworkMetaFontFamily = $this->setting($tenant, 'font_family_artwork_meta', $bodyFontFamily);
        $formFontFamily = $this->setting($tenant, 'font_family_form', $bodyFontFamily);
        $footerFontFamily = $this->setting($tenant, 'font_family_footer', $bodyFontFamily);
        $bodyFontSize = $this->setting($tenant, 'font_size_body', '18px');
        $headingFontSize = $this->setting($tenant, 'font_size_heading', '72px');
        $subheadingFontSize = $this->setting($tenant, 'font_size_subheading', '32px');
        $brandFontSize = $this->setting($tenant, 'font_size_brand', '52px');
        $navFontSize = $this->setting($tenant, 'font_size_nav', '15px');
        $proseFontSize = $this->setting($tenant, 'font_size_prose', '22px');
        $artworkTitleFontSize = $this->setting($tenant, 'font_size_artwork_title', '20px');
        $artworkMetaFontSize = $this->setting($tenant, 'font_size_artwork_meta', '15px');
        $formFontSize = $this->setting($tenant, 'font_size_form', '16px');
        $footerFontSize = $this->setting($tenant, 'font_size_footer', '15px');
        $bodyFontSelect = $this->fontSelect('font_family_body', $bodyFontFamily, $fontFamilies);
        $headingFontSelect = $this->fontSelect('font_family_heading', $headingFontFamily, $fontFamilies);
        $brandFontSelect = $this->fontSelect('font_family_brand', $brandFontFamily, $fontFamilies);
        $navFontSelect = $this->fontSelect('font_family_nav', $navFontFamily, $fontFamilies);
        $artworkTitleFontSelect = $this->fontSelect('font_family_artwork_title', $artworkTitleFontFamily, $fontFamilies);
        $artworkMetaFontSelect = $this->fontSelect('font_family_artwork_meta', $artworkMetaFontFamily, $fontFamilies);
        $formFontSelect = $this->fontSelect('font_family_form', $formFontFamily, $fontFamilies);
        $footerFontSelect = $this->fontSelect('font_family_footer', $footerFontFamily, $fontFamilies);
        $bodyFontPreview = $this->escape($bodyFontFamily);
        $headingFontPreview = $this->escape($headingFontFamily);
        $brandFontPreview = $this->escape($brandFontFamily);
        $navFontPreview = $this->escape($navFontFamily);
        $artworkTitleFontPreview = $this->escape($artworkTitleFontFamily);
        $artworkMetaFontPreview = $this->escape($artworkMetaFontFamily);
        $formFontPreview = $this->escape($formFontFamily);
        $footerFontPreview = $this->escape($footerFontFamily);
        $bodySizeControl = $this->fontSizeControl('font_size_body', 'Body text size', $bodyFontSize, 18, 12, 28, 'Body text size preview');
        $headingSizeControl = $this->fontSizeControl('font_size_heading', 'Main heading size', $headingFontSize, 72, 28, 120, 'Main heading size preview');
        $subheadingSizeControl = $this->fontSizeControl('font_size_subheading', 'Subheading size', $subheadingFontSize, 32, 18, 64, 'Subheading size preview');
        $brandSizeControl = $this->fontSizeControl('font_size_brand', 'Site title size', $brandFontSize, 52, 24, 96, 'Site title size preview');
        $navSizeControl = $this->fontSizeControl('font_size_nav', 'Navigation size', $navFontSize, 15, 11, 24, 'Navigation size preview');
        $proseSizeControl = $this->fontSizeControl('font_size_prose', 'Intro/prose size', $proseFontSize, 22, 14, 36, 'Intro/prose size preview');
        $artworkTitleSizeControl = $this->fontSizeControl('font_size_artwork_title', 'Artwork title size', $artworkTitleFontSize, 20, 12, 40, 'Artwork title size preview');
        $artworkMetaSizeControl = $this->fontSizeControl('font_size_artwork_meta', 'Artwork metadata size', $artworkMetaFontSize, 15, 10, 24, 'Artwork metadata size preview');
        $formSizeControl = $this->fontSizeControl('font_size_form', 'Forms size', $formFontSize, 16, 12, 26, 'Forms size preview');
        $footerSizeControl = $this->fontSizeControl('font_size_footer', 'Footer size', $footerFontSize, 15, 10, 24, 'Footer size preview');
        $primaryColor = $this->setting($tenant, 'primary_color', '#111111');
        $accentColor = $this->setting($tenant, 'accent_color', '#c9a85f');
        $textColor = $this->setting($tenant, 'text_color', '#1f1a14');
        $backgroundColor = $this->setting($tenant, 'background_color', '#f7f2e8');
        $topbarBackgroundColor = $this->setting($tenant, 'topbar_background_color', '');
        $topbarTextColor = $this->setting($tenant, 'topbar_text_color', $textColor);
        $topbarBackgroundOpacity = $this->setting($tenant, 'topbar_background_opacity', '0.86');
        $topbarMediaUuid = (string) $this->settings->get($tenant, 'topbar_media_uuid', '');
        $menuBackgroundColor = $this->setting($tenant, 'menu_background_color', $topbarBackgroundColor);
        $menuTextColor = $this->setting($tenant, 'menu_text_color', $topbarTextColor);
        $menuBackgroundOpacity = $this->setting($tenant, 'menu_background_opacity', '0.82');
        $menuBackgroundEnabled = $this->setting($tenant, 'menu_background_enabled', '1');
        $menuMediaUuid = (string) $this->settings->get($tenant, 'menu_media_uuid', '');
        $headingBackgroundColor = $this->setting($tenant, 'heading_background_color', 'rgba(255,255,255,0.72)');
        $headingBackgroundOpacity = $this->setting($tenant, 'heading_background_opacity', '0.72');
        $contentBackgroundColor = $this->setting($tenant, 'content_background_color', 'rgba(255,255,255,0.72)');
        $contentBackgroundOpacity = $this->setting($tenant, 'content_background_opacity', '0.00');
        $textBackgroundColor = $this->setting($tenant, 'text_background_color', 'rgba(255,255,255,0.68)');
        $textBackgroundOpacity = $this->setting($tenant, 'text_background_opacity', '0.72');
        $headerDropShadowEnabled = $this->setting($tenant, 'header_drop_shadow_enabled', '1');
        $headerDropShadow = $this->setting($tenant, 'header_drop_shadow', '0 18px 45px rgba(0,0,0,0.24)');
        $artworkCardBackgroundColor = $this->setting($tenant, 'artwork_card_background_color', '#fffaf0');
        $artworkCardBackgroundOpacity = $this->setting($tenant, 'artwork_card_background_opacity', '0.00');
        $artworkCardBackgroundSize = $this->setting($tenant, 'artwork_card_background_size', 'cover');
        $artworkCardMediaUuid = (string) $this->settings->get($tenant, 'artwork_card_media_uuid', '');
        $topbarPicker = $this->siteImagePicker($tenant, 'topbar_media_uuid', $topbarMediaUuid, true);
        $menuPicker = $this->siteImagePicker($tenant, 'menu_media_uuid', $menuMediaUuid, true);
        $artworkCardPicker = $this->siteImagePicker($tenant, 'artwork_card_media_uuid', $artworkCardMediaUuid, true);
        $exhibitionsHeading = $this->setting($tenant, 'exhibitions_heading', 'Recent exhibitions');
        $exhibitionsDisplayMode = (string) $this->settings->get($tenant, 'exhibitions_display_mode', 'text');
        $backgroundMode = (string) $this->settings->get($tenant, 'background_mode', 'single');
        $backgroundTileSize = $this->setting($tenant, 'background_tile_size', '360px');
        $backgroundOpacity = $this->setting($tenant, 'background_opacity', '0.12');
        $backgroundMediaUuid = (string) $this->settings->get($tenant, 'background_media_uuid', '');
        $backgroundPicker = $this->siteImagePicker($tenant, 'background_media_uuid', $backgroundMediaUuid, true);
        $tenantCss = $this->setting($tenant, 'tenant_css', '');
        $artworkDisplayOrder = (string) $this->settings->get($tenant, 'artwork_display_order', 'date_desc');
        $paletteButtons = $this->paletteButtons();
        $typographyPresetButtons = $this->typographyPresetButtons();
        $saveButton = '<div class="settings-section-actions"><button type="submit">Save site settings</button></div>';

        $selected = static fn (string $actual, string $expected): string => $actual === $expected ? ' selected' : '';
        $checked = static fn (string $actual, string $expected): string => $actual === $expected ? ' checked' : '';

        $settingsNav = $this->settingsSubnav($activeSection);
        $sectionAction = '/admin/settings?section=' . rawurlencode($activeSection);
        $identityContent = <<<HTML
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
HTML;
        $typographyContent = <<<HTML
        <fieldset>
            <legend>Typography</legend>
            <p class="admin-help">Choose font families and sizes for text on the public home, portfolio, about, and contact pages. Select a typography preset to fill the controls, then fine-tune individual fonts and sizes below.</p>
            {$typographyPresetButtons}
            <div class="admin-grid-2 tenant-typography-grid">
                <label>Body text font{$bodyFontSelect}<span class="font-picker-preview" data-font-preview="font_size_body" style="font-family: {$bodyFontPreview}; font-size: {$bodyFontSize}">Body text preview</span></label>
                {$bodySizeControl}
                <label>Heading font{$headingFontSelect}<span class="font-picker-preview" data-font-preview="font_size_heading" style="font-family: {$headingFontPreview}; font-size: {$headingFontSize}">Heading preview</span></label>
                {$headingSizeControl}
                {$subheadingSizeControl}
                <label>Site title font{$brandFontSelect}<span class="font-picker-preview" data-font-preview="font_size_brand" style="font-family: {$brandFontPreview}; font-size: {$brandFontSize}">Site title preview</span></label>
                {$brandSizeControl}
                <label>Navigation font{$navFontSelect}<span class="font-picker-preview" data-font-preview="font_size_nav" style="font-family: {$navFontPreview}; font-size: {$navFontSize}">Navigation preview</span></label>
                {$navSizeControl}
                {$proseSizeControl}
                <label>Artwork title font{$artworkTitleFontSelect}<span class="font-picker-preview" data-font-preview="font_size_artwork_title" style="font-family: {$artworkTitleFontPreview}; font-size: {$artworkTitleFontSize}">Artwork title preview</span></label>
                {$artworkTitleSizeControl}
                <label>Artwork metadata font{$artworkMetaFontSelect}<span class="font-picker-preview" data-font-preview="font_size_artwork_meta" style="font-family: {$artworkMetaFontPreview}; font-size: {$artworkMetaFontSize}">Artwork metadata preview</span></label>
                {$artworkMetaSizeControl}
                <label>Forms font{$formFontSelect}<span class="font-picker-preview" data-font-preview="font_size_form" style="font-family: {$formFontPreview}; font-size: {$formFontSize}">Form field preview</span></label>
                {$formSizeControl}
                <label>Footer font{$footerFontSelect}<span class="font-picker-preview" data-font-preview="font_size_footer" style="font-family: {$footerFontPreview}; font-size: {$footerFontSize}">Footer preview</span></label>
                {$footerSizeControl}
            </div>
        </fieldset>
HTML;
        $colorsContent = <<<HTML
        <fieldset>
            <legend>Colors and backgrounds</legend>
            {$paletteButtons}
            <div class="admin-grid-2">
                <label>Primary color<input name="primary_color" value="{$primaryColor}"></label>
                <label>Accent color<input name="accent_color" value="{$accentColor}"></label>
                <label>Default text color<input name="text_color" value="{$textColor}"></label>
                <label>Page background color<input name="background_color" value="{$backgroundColor}"></label>
                <label>Top bar background color<input name="topbar_background_color" value="{$topbarBackgroundColor}"></label>
                <label>Top bar text color<input name="topbar_text_color" value="{$topbarTextColor}"><span class="admin-help">Used for the site title against the top bar.</span></label>
                <label>Top bar opacity<input type="number" name="topbar_background_opacity" min="0" max="1" step="0.01" value="{$topbarBackgroundOpacity}"></label>
                <label>Menu background color<input name="menu_background_color" value="{$menuBackgroundColor}"></label>
                <label>Menu text color<input name="menu_text_color" value="{$menuTextColor}"><span class="admin-help">Used for nav links and logout against the menu panel.</span></label>
                <label>Menu background panel
                    <select name="menu_background_enabled">
                        <option value="1"{$selected($menuBackgroundEnabled, '1')}>Show panel</option>
                        <option value="0"{$selected($menuBackgroundEnabled, '0')}>Suppress panel</option>
                    </select>
                </label>
                <label>Menu opacity<input type="number" name="menu_background_opacity" min="0" max="1" step="0.01" value="{$menuBackgroundOpacity}"><span class="admin-help">Use 0, or suppress the panel, to remove the tan/nav wash.</span></label>
                <label>Heading spread color<input name="heading_background_color" value="{$headingBackgroundColor}"></label>
                <label>Heading spread opacity<input type="number" name="heading_background_opacity" min="0" max="1" step="0.01" value="{$headingBackgroundOpacity}"></label>
                <label>Content/artwork area background color<input name="content_background_color" value="{$contentBackgroundColor}"></label>
                <label>Content/artwork area opacity<input type="number" name="content_background_opacity" min="0" max="1" step="0.01" value="{$contentBackgroundOpacity}"></label>
                <label>Text spread color<input name="text_background_color" value="{$textBackgroundColor}"></label>
                <label>Text spread opacity<input type="number" name="text_background_opacity" min="0" max="1" step="0.01" value="{$textBackgroundOpacity}"></label>
                <label>Header drop shadow
                    <select name="header_drop_shadow_enabled">
                        <option value="1"{$selected($headerDropShadowEnabled, '1')}>On</option>
                        <option value="0"{$selected($headerDropShadowEnabled, '0')}>Off</option>
                    </select>
                </label>
                <label>Header shadow CSS<input name="header_drop_shadow" value="{$headerDropShadow}"></label>
                <label>Artwork card background color<input name="artwork_card_background_color" value="{$artworkCardBackgroundColor}"></label>
                <label>Artwork card opacity<input type="number" name="artwork_card_background_opacity" min="0" max="1" step="0.01" value="{$artworkCardBackgroundOpacity}"><span class="admin-help">Use 0 for no card wash over artwork; increase only when text needs a panel.</span></label>
                <label>Artwork card background size<input name="artwork_card_background_size" value="{$artworkCardBackgroundSize}"></label>
                <label>Background mode
                    <select name="background_mode">
                        <option value="single"{$selected($backgroundMode, 'single')}>Single image</option>
                        <option value="tile"{$selected($backgroundMode, 'tile')}>Tile</option>
                    </select>
                </label>
                <label>Background tile size<input name="background_tile_size" value="{$backgroundTileSize}"></label>
                <label>Background opacity<input type="number" name="background_opacity" min="0" max="1" step="0.01" value="{$backgroundOpacity}"></label>
            </div>
            <h3>Top bar background image</h3>
            {$topbarPicker}
            <h3>Menu background image</h3>
            {$menuPicker}
            <h3>Portfolio artwork card background image</h3>
            {$artworkCardPicker}
            <h3>Page background image</h3>
            {$backgroundPicker}
            <p class="admin-help">Only published artwork marked as Site Images appears here. Use opacity between 0 and 1; tile size accepts CSS values like 240px or 18rem.</p>
        </fieldset>
HTML;
        $miscContent = <<<HTML
        <fieldset>
            <legend>Sales notes</legend>
            <label>Public sales explanation<textarea name="sales_notes" rows="5">{$salesNotes}</textarea></label>
            <p class="admin-help">Shown on artwork detail pages beside price/contact actions. Use this for shipping, pickup, installation, and timing details.</p>
            <label>Stripe connected account ID<input name="stripe_connected_account_id" value="{$stripeConnectedAccountId}" placeholder="acct_..."></label>
            <p class="admin-help">Required for direct Stripe Connect payouts. Leave blank only during platform testing.</p>
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
            <p class="admin-help">Tenant public contact and email-list forms use the built-in ArtsFolio CAPTCHA. Cloudflare Turnstile is reserved for ArtsFolio platform-domain forms.</p>
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
HTML;
        $cssContent = <<<HTML
        <fieldset>
            <legend>Custom CSS</legend>
            <label>Custom CSS<textarea name="tenant_css" rows="18" spellcheck="false">{$tenantCss}</textarea></label>
            <p class="admin-help">This CSS is loaded after the default public stylesheet.</p>
        </fieldset>
HTML;

        $sectionContent = match ($activeSection) {
            'typography' => $typographyContent,
            'colors-backgrounds' => $colorsContent,
            'miscellaneous' => $miscContent,
            'custom-css' => $cssContent,
            default => $identityContent,
        };

        $body = <<<HTML
{$notice}
{$settingsNav}
<form class="plan-edit-form admin-form tenant-settings-form" method="post" action="{$sectionAction}">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <input type="hidden" name="settings_section" value="{$activeSection}">
        {$sectionContent}
        {$saveButton}
</form>
HTML;

        return Response::html((new TenantAdminLayout($this->settings))->render($tenant, 'Site settings: ' . $sectionLabel, $body, 'settings'));
    }

    public function update(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::html('<h1>Invalid request</h1><p>Security check failed.</p>', 419);
        }

        $activeSection = $this->activeSettingsSection((string) ($_POST['settings_section'] ?? $_GET['section'] ?? 'identity'));
        $keys = $this->settingsKeysForSection($activeSection);

        $before = [];
        $after = [];

        foreach ($keys as $key) {
            $before[$key] = $this->settings->get($tenant, $key, '');
            $value = trim((string) ($_POST[$key] ?? ''));
            if (in_array($key, ['background_media_uuid', 'topbar_media_uuid', 'menu_media_uuid', 'artwork_card_media_uuid'], true)) {
                $value = $this->safeSiteImageMediaUuid($tenant, $value);
            }
            if (str_ends_with($key, '_opacity')) {
                $value = $this->safeOpacity($value, in_array($key, ['background_opacity'], true) ? '0.12' : (in_array($key, ['artwork_card_background_opacity', 'content_background_opacity'], true) ? '0.00' : '0.72'));
            }
            if (in_array($key, ['header_drop_shadow_enabled', 'menu_background_enabled'], true)) {
                $value = $value === '0' ? '0' : '1';
            }
            if (str_ends_with($key, '_slug')) {
                $value = $this->safeSlug($value, str_replace('_slug', '', $key));
            }
            if (str_starts_with($key, 'font_family_')) {
                $value = $this->safeFontFamily($value);
            }
            if (str_starts_with($key, 'font_size_')) {
                $value = $this->safePublicFontSize($value, $this->defaultFontSize($key));
            }
            $this->settings->set($tenant, $key, $value);
            $after[$key] = $value;
        }

        $this->auditAction($request, $tenant, $currentUser, ['before' => $before, 'after' => $after]);

        return new Response('', 303, ['Location' => '/admin/settings?section=' . rawurlencode($activeSection) . '&notice=saved']);
    }


    /**
     * Returns the tenant settings subpages in display order.
     *
     * @return array<string, array{label: string, help: string}>
     */
    private function settingsSections(): array
    {
        return [
            'identity' => [
                'label' => 'Identity',
                'help' => 'Site identity, home intro text, public navigation labels, and public slugs.',
            ],
            'typography' => [
                'label' => 'Typography',
                'help' => 'Public font families, typography presets, and text sizing.',
            ],
            'colors-backgrounds' => [
                'label' => 'Colors & Backgrounds',
                'help' => 'Color palettes, nav colors, page backgrounds, and site image backgrounds.',
            ],
            'miscellaneous' => [
                'label' => 'Miscellaneous',
                'help' => 'Sales notes, artwork ordering, spam protection notes, and exhibition display options.',
            ],
            'custom-css' => [
                'label' => 'Custom CSS',
                'help' => 'Advanced tenant CSS loaded after the standard public stylesheet.',
            ],
        ];
    }

    /**
     * Normalizes the requested settings subpage to a known section key.
     */
    private function activeSettingsSection(string $section): string
    {
        $section = strtolower(trim($section));

        return array_key_exists($section, $this->settingsSections()) ? $section : 'identity';
    }

    /**
     * Renders tenant settings subpage navigation.
     */
    private function settingsSubnav(string $activeSection): string
    {
        $links = '';
        foreach ($this->settingsSections() as $key => $section) {
            $label = $this->escape($section['label']);
            $help = $this->escape($section['help']);
            $activeClass = $key === $activeSection ? ' is-active' : '';
            $ariaCurrent = $key === $activeSection ? ' aria-current="page"' : '';
            $href = '/admin/settings?section=' . rawurlencode($key);
            $links .= '<a class="tenant-settings-subnav-link' . $activeClass . '" href="' . $href . '" title="' . $help . '"' . $ariaCurrent . '>' . $label . '</a>';
        }

        return '<nav class="tenant-settings-subnav" aria-label="Settings sections">' . $links . '</nav>';
    }

    /**
     * Returns the persisted setting keys owned by a settings subpage.
     *
     * Saving one subpage must not clear values from fields that are hidden on
     * another subpage, so POST handling only writes the keys for the active page.
     *
     * @return list<string>
     */
    private function settingsKeysForSection(string $section): array
    {
        return match ($section) {
            'typography' => [
                'font_family_body', 'font_family_heading', 'font_family_brand', 'font_family_nav', 'font_family_artwork_title', 'font_family_artwork_meta', 'font_family_form', 'font_family_footer',
                'font_size_body', 'font_size_heading', 'font_size_subheading', 'font_size_brand', 'font_size_nav', 'font_size_prose', 'font_size_artwork_title', 'font_size_artwork_meta', 'font_size_form', 'font_size_footer',
            ],
            'colors-backgrounds' => [
                'primary_color', 'accent_color', 'text_color', 'background_color', 'topbar_background_color', 'topbar_text_color', 'topbar_background_opacity', 'topbar_media_uuid',
                'menu_background_color', 'menu_text_color', 'menu_background_enabled', 'menu_background_opacity', 'menu_media_uuid', 'heading_background_color', 'heading_background_opacity',
                'content_background_color', 'content_background_opacity', 'text_background_color', 'text_background_opacity', 'header_drop_shadow_enabled', 'header_drop_shadow', 'artwork_card_background_color', 'artwork_card_background_opacity', 'artwork_card_background_size', 'artwork_card_media_uuid', 'background_media_uuid',
                'background_mode', 'background_tile_size', 'background_opacity',
            ],
            'miscellaneous' => [
                'sales_notes', 'stripe_connected_account_id', 'artwork_display_order', 'exhibitions_heading', 'exhibitions_display_mode',
            ],
            'custom-css' => [
                'tenant_css',
            ],
            default => [
                'site_title', 'artist_name', 'browser_title', 'copyright_name', 'site_admin_email', 'home_intro',
                'home_tab', 'portfolio_tab', 'about_tab', 'contact_tab', 'portfolio_slug', 'about_slug', 'contact_slug',
            ],
        };
    }

    /**
     * Returns public-safe font family choices for tenant typography controls.
     *
     * The values intentionally use system/local font stacks only so tenant pages
     * do not depend on external font hosts, tracking requests, or slow downloads.
     *
     * @return array<string, string>
     */
    private function fontFamilyOptions(): array
    {
        return [
            'Inter, ui-sans-serif, system-ui, sans-serif' => 'Inter / modern sans',
            '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif' => 'System UI / native sans',
            'Helvetica Neue, Helvetica, Arial, ui-sans-serif, sans-serif' => 'Helvetica / neutral sans',
            'Arial, Helvetica, ui-sans-serif, sans-serif' => 'Arial / clean sans',
            'Verdana, Geneva, ui-sans-serif, sans-serif' => 'Verdana / screen friendly',
            'Trebuchet MS, Lucida Grande, Lucida Sans Unicode, Lucida Sans, Arial, sans-serif' => 'Trebuchet / casual sans',
            'Avenir, "Avenir Next", Montserrat, ui-sans-serif, system-ui, sans-serif' => 'Avenir / clean geometric',
            'Futura, "Trebuchet MS", Arial, ui-sans-serif, sans-serif' => 'Futura / modernist',
            'Gill Sans, Gill Sans MT, Calibri, ui-sans-serif, sans-serif' => 'Gill Sans / humanist',
            'Optima, Candara, "Noto Sans", source-sans-pro, sans-serif' => 'Optima / elegant sans',
            'ui-serif, Georgia, Cambria, "Times New Roman", Times, serif' => 'Serif / editorial',
            'Georgia, "Times New Roman", Times, serif' => 'Georgia / gallery text',
            'Palatino, "Palatino Linotype", "Book Antiqua", Georgia, serif' => 'Palatino / literary serif',
            'Baskerville, "Libre Baskerville", Georgia, serif' => 'Baskerville / formal serif',
            'Garamond, "Times New Roman", Times, serif' => 'Garamond / bookish serif',
            'Charter, "Bitstream Charter", Georgia, serif' => 'Charter / readable serif',
            'Hoefler Text, "Iowan Old Style", "Times New Roman", serif' => 'Hoefler / refined serif',
            'Didot, "Bodoni 72", "Bodoni 72 Smallcaps", Georgia, serif' => 'Didot / high contrast',
            'Copperplate, Copperplate Gothic Light, fantasy' => 'Copperplate / engraved display',
            'Rockwell, "Courier Bold", Courier, Georgia, serif' => 'Rockwell / slab display',
            'Menlo, Monaco, Consolas, "Liberation Mono", monospace' => 'Monospace / technical',
            'Courier New, Courier, monospace' => 'Courier / typewriter',
        ];
    }

    /**
     * Renders quick preset buttons for the typography controls.
     */
    private function typographyPresetButtons(): string
    {
        $buttons = '';

        foreach ($this->typographyPresets() as $preset) {
            $data = htmlspecialchars(json_encode($preset['values'], JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8');
            $name = $this->escape($preset['name']);
            $description = $this->escape($preset['description']);
            $tone = $this->escape($preset['tone']);
            $heading = $this->escape($preset['values']['font_family_heading']);
            $body = $this->escape($preset['values']['font_family_body']);
            $accent = $this->escape($preset['accent']);

            $buttons .= <<<HTML
<button type="button" class="tenant-typography-preset-button" data-typography-preset="{$data}" style="--typography-preset-heading: {$heading}; --typography-preset-body: {$body}; --typography-preset-accent: {$accent};">
    <span class="typography-preset-name">{$name}</span>
    <span class="typography-preset-tone">{$tone}</span>
    <span class="typography-preset-heading">Sample heading</span>
    <span class="typography-preset-body">Sample body text</span>
    <span class="typography-preset-description">{$description}</span>
</button>
HTML;
        }

        return '<div class="tenant-typography-presets" aria-label="Typography presets">' . $buttons . '</div>';
    }

    /**
     * Returns public-safe typography presets. Applying a preset only fills the
     * editable font and size controls; tenants can continue tweaking afterward.
     *
     * @return list<array{name: string, tone: string, description: string, accent: string, values: array<string, string>}>
     */
    private function typographyPresets(): array
    {
        $modernSans = 'Inter, ui-sans-serif, system-ui, sans-serif';
        $nativeSans = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
        $editorial = 'ui-serif, Georgia, Cambria, "Times New Roman", Times, serif';
        $georgia = 'Georgia, "Times New Roman", Times, serif';
        $baskerville = 'Baskerville, "Libre Baskerville", Georgia, serif';
        $didot = 'Didot, "Bodoni 72", "Bodoni 72 Smallcaps", Georgia, serif';
        $futura = 'Futura, "Trebuchet MS", Arial, ui-sans-serif, sans-serif';
        $optima = 'Optima, Candara, "Noto Sans", source-sans-pro, sans-serif';
        $menlo = 'Menlo, Monaco, Consolas, "Liberation Mono", monospace';
        $palatino = 'Palatino, "Palatino Linotype", "Book Antiqua", Georgia, serif';
        $gill = 'Gill Sans, Gill Sans MT, Calibri, ui-sans-serif, sans-serif';
        $rockwell = 'Rockwell, "Courier Bold", Courier, Georgia, serif';

        return [
            [
                'name' => 'Clean Gallery',
                'tone' => 'balanced sans',
                'description' => 'Modern, quiet, and legible for image-first portfolios.',
                'accent' => '#44546a',
                'values' => [
                    'font_family_body' => $modernSans,
                    'font_family_heading' => $modernSans,
                    'font_family_brand' => $modernSans,
                    'font_family_nav' => $modernSans,
                    'font_family_artwork_title' => $modernSans,
                    'font_family_artwork_meta' => $modernSans,
                    'font_family_form' => $modernSans,
                    'font_family_footer' => $modernSans,
                    'font_size_body' => '18px',
                    'font_size_heading' => '68px',
                    'font_size_subheading' => '30px',
                    'font_size_brand' => '48px',
                    'font_size_nav' => '15px',
                    'font_size_prose' => '22px',
                    'font_size_artwork_title' => '20px',
                    'font_size_artwork_meta' => '15px',
                    'font_size_form' => '16px',
                    'font_size_footer' => '15px',
                ],
            ],
            [
                'name' => 'Editorial Serif',
                'tone' => 'magazine serif',
                'description' => 'Serif body with crisp sans navigation for essays and statements.',
                'accent' => '#8c6239',
                'values' => [
                    'font_family_body' => $editorial,
                    'font_family_heading' => $baskerville,
                    'font_family_brand' => $baskerville,
                    'font_family_nav' => $nativeSans,
                    'font_family_artwork_title' => $baskerville,
                    'font_family_artwork_meta' => $nativeSans,
                    'font_family_form' => $nativeSans,
                    'font_family_footer' => $editorial,
                    'font_size_body' => '19px',
                    'font_size_heading' => '72px',
                    'font_size_subheading' => '32px',
                    'font_size_brand' => '54px',
                    'font_size_nav' => '15px',
                    'font_size_prose' => '23px',
                    'font_size_artwork_title' => '22px',
                    'font_size_artwork_meta' => '15px',
                    'font_size_form' => '16px',
                    'font_size_footer' => '15px',
                ],
            ],
            [
                'name' => 'Museum Label',
                'tone' => 'formal quiet',
                'description' => 'Refined headings, restrained labels, and readable catalog text.',
                'accent' => '#6c6a5f',
                'values' => [
                    'font_family_body' => $georgia,
                    'font_family_heading' => $didot,
                    'font_family_brand' => $didot,
                    'font_family_nav' => $optima,
                    'font_family_artwork_title' => $didot,
                    'font_family_artwork_meta' => $optima,
                    'font_family_form' => $optima,
                    'font_family_footer' => $georgia,
                    'font_size_body' => '18px',
                    'font_size_heading' => '78px',
                    'font_size_subheading' => '30px',
                    'font_size_brand' => '58px',
                    'font_size_nav' => '14px',
                    'font_size_prose' => '21px',
                    'font_size_artwork_title' => '22px',
                    'font_size_artwork_meta' => '14px',
                    'font_size_form' => '16px',
                    'font_size_footer' => '14px',
                ],
            ],
            [
                'name' => 'Poster Modern',
                'tone' => 'bold geometric',
                'description' => 'Large modernist display typography with sturdy sans details.',
                'accent' => '#ff6b35',
                'values' => [
                    'font_family_body' => $nativeSans,
                    'font_family_heading' => $futura,
                    'font_family_brand' => $futura,
                    'font_family_nav' => $futura,
                    'font_family_artwork_title' => $futura,
                    'font_family_artwork_meta' => $nativeSans,
                    'font_family_form' => $nativeSans,
                    'font_family_footer' => $nativeSans,
                    'font_size_body' => '18px',
                    'font_size_heading' => '88px',
                    'font_size_subheading' => '34px',
                    'font_size_brand' => '64px',
                    'font_size_nav' => '16px',
                    'font_size_prose' => '22px',
                    'font_size_artwork_title' => '24px',
                    'font_size_artwork_meta' => '15px',
                    'font_size_form' => '16px',
                    'font_size_footer' => '15px',
                ],
            ],
            [
                'name' => 'Studio Notes',
                'tone' => 'technical calm',
                'description' => 'Monospace metadata with humanist text for process-heavy work.',
                'accent' => '#1463ff',
                'values' => [
                    'font_family_body' => $optima,
                    'font_family_heading' => $optima,
                    'font_family_brand' => $optima,
                    'font_family_nav' => $menlo,
                    'font_family_artwork_title' => $optima,
                    'font_family_artwork_meta' => $menlo,
                    'font_family_form' => $menlo,
                    'font_family_footer' => $menlo,
                    'font_size_body' => '18px',
                    'font_size_heading' => '64px',
                    'font_size_subheading' => '28px',
                    'font_size_brand' => '46px',
                    'font_size_nav' => '14px',
                    'font_size_prose' => '21px',
                    'font_size_artwork_title' => '20px',
                    'font_size_artwork_meta' => '13px',
                    'font_size_form' => '15px',
                    'font_size_footer' => '13px',
                ],
            ],
            [
                'name' => 'Bookish Warmth',
                'tone' => 'warm literary',
                'description' => 'Classic book typography, good for about pages and longer prose.',
                'accent' => '#a35d35',
                'values' => [
                    'font_family_body' => $palatino,
                    'font_family_heading' => $palatino,
                    'font_family_brand' => $palatino,
                    'font_family_nav' => $gill,
                    'font_family_artwork_title' => $palatino,
                    'font_family_artwork_meta' => $gill,
                    'font_family_form' => $gill,
                    'font_family_footer' => $palatino,
                    'font_size_body' => '19px',
                    'font_size_heading' => '66px',
                    'font_size_subheading' => '30px',
                    'font_size_brand' => '50px',
                    'font_size_nav' => '15px',
                    'font_size_prose' => '24px',
                    'font_size_artwork_title' => '21px',
                    'font_size_artwork_meta' => '15px',
                    'font_size_form' => '16px',
                    'font_size_footer' => '15px',
                ],
            ],
            [
                'name' => 'Slab Signal',
                'tone' => 'graphic punch',
                'description' => 'Slab display type for bolder, more poster-like artist sites.',
                'accent' => '#c45d75',
                'values' => [
                    'font_family_body' => $nativeSans,
                    'font_family_heading' => $rockwell,
                    'font_family_brand' => $rockwell,
                    'font_family_nav' => $nativeSans,
                    'font_family_artwork_title' => $rockwell,
                    'font_family_artwork_meta' => $nativeSans,
                    'font_family_form' => $nativeSans,
                    'font_family_footer' => $nativeSans,
                    'font_size_body' => '18px',
                    'font_size_heading' => '76px',
                    'font_size_subheading' => '30px',
                    'font_size_brand' => '56px',
                    'font_size_nav' => '15px',
                    'font_size_prose' => '22px',
                    'font_size_artwork_title' => '22px',
                    'font_size_artwork_meta' => '15px',
                    'font_size_form' => '16px',
                    'font_size_footer' => '15px',
                ],
            ],
        ];
    }

    /**
     * Renders a font picker select with previewable option labels.
     *
     * @param array<string, string> $fonts
     */
    private function fontSelect(string $name, string $selectedValue, array $fonts): string
    {
        $options = '';
        $selectedValue = $this->safeFontFamily($selectedValue);

        foreach ($fonts as $value => $label) {
            $safeValue = $this->escape($value);
            $safeLabel = $this->escape($label);
            $selected = $value === $selectedValue ? ' selected' : '';
            $options .= '<option value="' . $safeValue . '" style="font-family:' . $safeValue . '"' . $selected . '>' . $safeLabel . '</option>';
        }

        return '<select class="tenant-font-picker" name="' . $this->escape($name) . '">' . $options . '</select>';
    }

    /**
     * Restricts saved font families to the curated local/system list.
     */
    private function safeFontFamily(string $value): string
    {
        $value = trim($value);
        $fonts = $this->fontFamilyOptions();

        return array_key_exists($value, $fonts) ? $value : 'Inter, ui-sans-serif, system-ui, sans-serif';
    }

    /**
     * Restricts public font sizes to conservative CSS size expressions.
     */
    private function safePublicFontSize(string $value, string $default): string
    {
        $value = trim($value);

        if ($value === '') {
            return $default;
        }

        if (preg_match('/^(?:[0-9]+(?:\.[0-9]+)?(?:px|rem|em|%)|clamp\([0-9.]+(?:px|rem|em|%),\s*[0-9.]+(?:vw|vh|rem|em|%),\s*[0-9.]+(?:px|rem|em|%)\))$/', $value) === 1) {
            return $value;
        }

        return $default;
    }

    /**
     * Renders a friendly pixel-based font size control for tenant typography.
     *
     * The stored setting remains a CSS size string, but the UI exposes a slider
     * and numeric field because raw clamp()/rem values are hostile to normal
     * tenant editing. Existing rem/clamp values are converted to a practical
     * pixel approximation for display and saved back as px.
     */
    private function fontSizeControl(string $name, string $label, string $value, int $defaultPx, int $minPx, int $maxPx, string $sample): string
    {
        $pixels = $this->fontSizePixels($value, $defaultPx);
        $safeName = $this->escape($name);
        $safeLabel = $this->escape($label);
        $safeSample = $this->escape($sample);

        return <<<HTML
<label class="tenant-font-size-control" data-font-size-control="{$safeName}">
    <span class="tenant-font-size-label">{$safeLabel}</span>
    <span class="tenant-font-size-row">
        <input class="tenant-font-size-range" type="range" min="{$minPx}" max="{$maxPx}" step="1" value="{$pixels}" data-font-size-range="{$safeName}" aria-label="{$safeLabel}">
        <input class="tenant-font-size-number" type="number" min="{$minPx}" max="{$maxPx}" step="1" value="{$pixels}" data-font-size-number="{$safeName}" aria-label="{$safeLabel} pixels">
        <input type="hidden" name="{$safeName}" value="{$pixels}px" data-font-size-value="{$safeName}">
        <span class="tenant-font-size-unit">px</span>
    </span>
    <span class="admin-help">Drag the slider or type a pixel size. The matching text preview updates above.</span>
</label>
HTML;
    }

    /**
     * Converts stored CSS size strings to a friendly pixel value for sliders.
     */
    private function fontSizePixels(string $value, int $defaultPx): int
    {
        $value = trim($value);
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)px$/', $value, $matches) === 1) {
            return max(8, min(160, (int) round((float) $matches[1])));
        }
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)rem$/', $value, $matches) === 1) {
            return max(8, min(160, (int) round((float) $matches[1] * 16)));
        }
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)em$/', $value, $matches) === 1) {
            return max(8, min(160, (int) round((float) $matches[1] * 16)));
        }

        return $defaultPx;
    }

    /**
     * Provides per-field typography defaults for validation fallbacks.
     */
    private function defaultFontSize(string $key): string
    {
        return match ($key) {
            'font_size_heading' => '72px',
            'font_size_subheading' => '32px',
            'font_size_brand' => '52px',
            'font_size_nav' => '15px',
            'font_size_prose' => '22px',
            'font_size_artwork_title' => '20px',
            'font_size_artwork_meta' => '15px',
            'font_size_form' => '16px',
            'font_size_footer' => '15px',
            default => '18px',
        };
    }

    /**
     * Renders quick palette buttons for the color/background controls.
     */
    private function paletteButtons(): string
    {
        $buttons = '';

        foreach ($this->palettes() as $palette) {
            $data = htmlspecialchars(json_encode($palette['values'], JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8');
            $name = $this->escape($palette['name']);
            $description = $this->escape($palette['description']);
            $tone = $this->escape($palette['tone']);
            $buttonBackground = $this->escape($palette['button_background']);
            $buttonText = $this->escape($palette['button_text']);
            $buttonAccent = $this->escape($palette['button_accent']);
            $buttonTopbar = $this->escape($palette['values']['topbar_background_color'] ?? $palette['button_background']);
            $buttonTopbarText = $this->escape($palette['values']['topbar_text_color'] ?? $palette['button_text']);
            $buttonMenu = $this->escape($palette['values']['menu_background_color'] ?? $palette['button_background']);
            $buttonMenuText = $this->escape($palette['values']['menu_text_color'] ?? $palette['button_text']);
            $buttonPage = $this->escape($palette['values']['background_color'] ?? $palette['button_background']);
            $buttonSurface = $this->escape($palette['values']['text_background_color'] ?? $palette['button_background']);
            $swatches = '';

            foreach ($palette['swatches'] as $swatch) {
                $safeSwatch = $this->escape($swatch);
                $swatches .= '<span class="tenant-palette-swatch" style="--palette-swatch:' . $safeSwatch . '" aria-hidden="true"></span>';
            }

            $buttons .= <<<HTML
<button type="button" class="tenant-palette-button" data-tenant-palette="{$data}" data-palette-tone="{$tone}" style="--palette-button-bg: {$buttonBackground}; --palette-button-text: {$buttonText}; --palette-button-accent: {$buttonAccent}; --palette-button-topbar: {$buttonTopbar}; --palette-button-topbar-text: {$buttonTopbarText}; --palette-button-menu: {$buttonMenu}; --palette-button-menu-text: {$buttonMenuText}; --palette-button-page: {$buttonPage}; --palette-button-surface: {$buttonSurface};">
    <span class="tenant-palette-preview" aria-hidden="true">
        <span class="tenant-palette-preview-topbar">Aa</span>
        <span class="tenant-palette-preview-menu">Nav</span>
        <span class="tenant-palette-preview-page"></span>
    </span>
    <span class="tenant-palette-name">{$name}</span>
    <span class="tenant-palette-swatches">{$swatches}</span>
    <span class="tenant-palette-description">{$description}</span>
</button>
HTML;
        }

        return <<<HTML
<div class="tenant-palette-toolbar" aria-label="Color palette presets">
    <p class="admin-help">Choose a palette to fill the color and background controls below. After applying one, adjust any individual picker or field before saving.</p>
    <div class="tenant-palette-grid">
        {$buttons}
    </div>
</div>
HTML;
    }

    /**
     * Defines tenant color/background presets. The first palette is the platform default for new sites.
     *
     * @return array<int, array{name: string, description: string, tone: string, button_background: string, button_text: string, button_accent: string, swatches: array<int, string>, values: array<string, string>}>
     */
    private function palettes(): array
    {
        return [
            [
                'name' => 'Default',
                'description' => 'Warm paper, black text, muted gold accent.',
                'tone' => 'warm neutral',
                'button_background' => '#f7f2e8',
                'button_text' => '#1f1a14',
                'button_accent' => '#c9a85f',
                'swatches' => ['#f7f2e8', '#1f1a14', '#111111', '#c9a85f'],
                'values' => [
                    'primary_color' => '#111111',
                    'accent_color' => '#c9a85f',
                    'text_color' => '#1f1a14',
                    'background_color' => '#f7f2e8',
                    'topbar_background_color' => '#f7f2e8',
                    'topbar_text_color' => '#111111',
                    'topbar_background_opacity' => '0.86',
                    'menu_background_color' => '#f7f2e8',
                    'menu_text_color' => '#1f1a14',
                    'menu_background_enabled' => '1',
                    'menu_background_opacity' => '0.82',
                    'heading_background_color' => '#fff8ec',
                    'heading_background_opacity' => '0.72',
                    'content_background_color' => '#fffaf0',
                    'content_background_opacity' => '0',
                    'text_background_color' => '#fff7e8',
                    'text_background_opacity' => '0.72',
                    'artwork_card_background_color' => '#fffaf0',
                    'artwork_card_background_opacity' => '0',
                    'background_mode' => 'single',
                    'background_tile_size' => '360px',
                    'background_opacity' => '0.12',
                    'header_drop_shadow_enabled' => '1',
                    'header_drop_shadow' => '0 18px 45px rgba(0,0,0,0.24)',
                ],
            ],
            [
                'name' => 'Gallery White',
                'description' => 'Clean wall, charcoal type, quiet blue accent.',
                'tone' => 'cool neutral',
                'button_background' => '#ffffff',
                'button_text' => '#202124',
                'button_accent' => '#44546a',
                'swatches' => ['#ffffff', '#202124', '#44546a', '#e9edf2'],
                'values' => [
                    'primary_color' => '#202124',
                    'accent_color' => '#44546a',
                    'text_color' => '#202124',
                    'background_color' => '#ffffff',
                    'topbar_background_color' => '#ffffff',
                    'topbar_text_color' => '#202124',
                    'topbar_background_opacity' => '0.94',
                    'menu_background_color' => '#ffffff',
                    'menu_text_color' => '#202124',
                    'menu_background_enabled' => '1',
                    'menu_background_opacity' => '0.9',
                    'heading_background_color' => '#f3f5f7',
                    'heading_background_opacity' => '0.62',
                    'content_background_color' => '#ffffff',
                    'content_background_opacity' => '0',
                    'text_background_color' => '#ffffff',
                    'text_background_opacity' => '0.7',
                    'artwork_card_background_color' => '#ffffff',
                    'artwork_card_background_opacity' => '0',
                    'background_mode' => 'single',
                    'background_tile_size' => '360px',
                    'background_opacity' => '0.05',
                    'header_drop_shadow_enabled' => '1',
                    'header_drop_shadow' => '0 14px 34px rgba(0,0,0,0.12)',
                ],
            ],
            [
                'name' => 'Ink Studio',
                'description' => 'Deep ink surfaces with warm bone panels.',
                'tone' => 'dark warm',
                'button_background' => '#151515',
                'button_text' => '#f4efe4',
                'button_accent' => '#d5a85d',
                'swatches' => ['#151515', '#f4efe4', '#d5a85d', '#29241f'],
                'values' => [
                    'primary_color' => '#f4efe4',
                    'accent_color' => '#d5a85d',
                    'text_color' => '#f4efe4',
                    'background_color' => '#151515',
                    'topbar_background_color' => '#151515',
                    'topbar_text_color' => '#f4efe4',
                    'topbar_background_opacity' => '0.9',
                    'menu_background_color' => '#29241f',
                    'menu_text_color' => '#f4efe4',
                    'menu_background_enabled' => '1',
                    'menu_background_opacity' => '0.9',
                    'heading_background_color' => '#29241f',
                    'heading_background_opacity' => '0.78',
                    'content_background_color' => '#151515',
                    'content_background_opacity' => '0.15',
                    'text_background_color' => '#29241f',
                    'text_background_opacity' => '0.72',
                    'artwork_card_background_color' => '#1f1c18',
                    'artwork_card_background_opacity' => '0.12',
                    'background_mode' => 'single',
                    'background_tile_size' => '360px',
                    'background_opacity' => '0.1',
                    'header_drop_shadow_enabled' => '1',
                    'header_drop_shadow' => '0 18px 50px rgba(0,0,0,0.45)',
                ],
            ],
            [
                'name' => 'Desert Clay',
                'description' => 'Clay, sand, rust, and dark espresso text.',
                'tone' => 'warm earthy',
                'button_background' => '#efe0cc',
                'button_text' => '#3a2418',
                'button_accent' => '#b45d35',
                'swatches' => ['#efe0cc', '#3a2418', '#b45d35', '#d49f6a'],
                'values' => [
                    'primary_color' => '#3a2418',
                    'accent_color' => '#b45d35',
                    'text_color' => '#3a2418',
                    'background_color' => '#efe0cc',
                    'topbar_background_color' => '#e6c7a5',
                    'topbar_text_color' => '#3a2418',
                    'topbar_background_opacity' => '0.9',
                    'menu_background_color' => '#e6c7a5',
                    'menu_text_color' => '#3a2418',
                    'menu_background_enabled' => '1',
                    'menu_background_opacity' => '0.84',
                    'heading_background_color' => '#f6eadc',
                    'heading_background_opacity' => '0.68',
                    'content_background_color' => '#f7ead9',
                    'content_background_opacity' => '0.08',
                    'text_background_color' => '#f6eadc',
                    'text_background_opacity' => '0.7',
                    'artwork_card_background_color' => '#f6eadc',
                    'artwork_card_background_opacity' => '0.04',
                    'background_mode' => 'single',
                    'background_tile_size' => '360px',
                    'background_opacity' => '0.1',
                    'header_drop_shadow_enabled' => '1',
                    'header_drop_shadow' => '0 16px 42px rgba(92,54,28,0.24)',
                ],
            ],
            [
                'name' => 'Forest Linen',
                'description' => 'Moss, linen, walnut, and soft sage.',
                'tone' => 'green natural',
                'button_background' => '#edf0e4',
                'button_text' => '#253322',
                'button_accent' => '#6f8f5b',
                'swatches' => ['#eef0df', '#203224', '#6f7f4f', '#c9d0a3'],
                'values' => [
                    'primary_color' => '#203224',
                    'accent_color' => '#6f7f4f',
                    'text_color' => '#203224',
                    'background_color' => '#eef0df',
                    'topbar_background_color' => '#dfe6c8',
                    'topbar_text_color' => '#203224',
                    'topbar_background_opacity' => '0.88',
                    'menu_background_color' => '#dfe6c8',
                    'menu_text_color' => '#203224',
                    'menu_background_enabled' => '1',
                    'menu_background_opacity' => '0.82',
                    'heading_background_color' => '#f5f3e7',
                    'heading_background_opacity' => '0.68',
                    'content_background_color' => '#f5f3e7',
                    'content_background_opacity' => '0.05',
                    'text_background_color' => '#f5f3e7',
                    'text_background_opacity' => '0.72',
                    'artwork_card_background_color' => '#f5f3e7',
                    'artwork_card_background_opacity' => '0.04',
                    'background_mode' => 'single',
                    'background_tile_size' => '360px',
                    'background_opacity' => '0.08',
                    'header_drop_shadow_enabled' => '1',
                    'header_drop_shadow' => '0 16px 40px rgba(32,50,36,0.2)',
                ],
            ],
            [
                'name' => 'Signal Blue',
                'description' => 'Cold blue field with bright signal accent.',
                'tone' => 'cool electric',
                'button_background' => '#e8f1ff',
                'button_text' => '#10233f',
                'button_accent' => '#1463ff',
                'swatches' => ['#e8f0f7', '#142033', '#0f6b8f', '#f2b84b'],
                'values' => [
                    'primary_color' => '#142033',
                    'accent_color' => '#0f6b8f',
                    'text_color' => '#142033',
                    'background_color' => '#e8f0f7',
                    'topbar_background_color' => '#d8e7f2',
                    'topbar_text_color' => '#142033',
                    'topbar_background_opacity' => '0.9',
                    'menu_background_color' => '#d8e7f2',
                    'menu_text_color' => '#142033',
                    'menu_background_enabled' => '1',
                    'menu_background_opacity' => '0.86',
                    'heading_background_color' => '#f6fbff',
                    'heading_background_opacity' => '0.7',
                    'content_background_color' => '#f6fbff',
                    'content_background_opacity' => '0.06',
                    'text_background_color' => '#f6fbff',
                    'text_background_opacity' => '0.72',
                    'artwork_card_background_color' => '#f6fbff',
                    'artwork_card_background_opacity' => '0.04',
                    'background_mode' => 'single',
                    'background_tile_size' => '360px',
                    'background_opacity' => '0.08',
                    'header_drop_shadow_enabled' => '1',
                    'header_drop_shadow' => '0 16px 44px rgba(20,32,51,0.22)',
                ],
            ],
            [
                'name' => 'Rose Paper',
                'description' => 'Soft blush paper, plum text, copper accent.',
                'tone' => 'warm rose',
                'button_background' => '#fff0f2',
                'button_text' => '#3a2028',
                'button_accent' => '#c45d75',
                'swatches' => ['#f6e7df', '#321824', '#ad6a5a', '#f8f0ea'],
                'values' => [
                    'primary_color' => '#321824',
                    'accent_color' => '#ad6a5a',
                    'text_color' => '#321824',
                    'background_color' => '#f6e7df',
                    'topbar_background_color' => '#f1d5cb',
                    'topbar_text_color' => '#321824',
                    'topbar_background_opacity' => '0.88',
                    'menu_background_color' => '#f1d5cb',
                    'menu_text_color' => '#321824',
                    'menu_background_enabled' => '1',
                    'menu_background_opacity' => '0.82',
                    'heading_background_color' => '#fff4ef',
                    'heading_background_opacity' => '0.7',
                    'content_background_color' => '#fff4ef',
                    'content_background_opacity' => '0.06',
                    'text_background_color' => '#fff4ef',
                    'text_background_opacity' => '0.72',
                    'artwork_card_background_color' => '#fff4ef',
                    'artwork_card_background_opacity' => '0.03',
                    'background_mode' => 'single',
                    'background_tile_size' => '360px',
                    'background_opacity' => '0.08',
                    'header_drop_shadow_enabled' => '1',
                    'header_drop_shadow' => '0 16px 42px rgba(50,24,36,0.18)',
                ],
            ],
            [
                'name' => 'Concrete Pop',
                'description' => 'Neutral concrete with black type and hot accent.',
                'tone' => 'cool industrial',
                'button_background' => '#ecebe7',
                'button_text' => '#1e2227',
                'button_accent' => '#ff6b35',
                'swatches' => ['#e6e2da', '#151515', '#ff5a3d', '#f8f7f4'],
                'values' => [
                    'primary_color' => '#151515',
                    'accent_color' => '#ff5a3d',
                    'text_color' => '#151515',
                    'background_color' => '#e6e2da',
                    'topbar_background_color' => '#f8f7f4',
                    'topbar_text_color' => '#151515',
                    'topbar_background_opacity' => '0.9',
                    'menu_background_color' => '#f8f7f4',
                    'menu_text_color' => '#151515',
                    'menu_background_enabled' => '1',
                    'menu_background_opacity' => '0.84',
                    'heading_background_color' => '#f8f7f4',
                    'heading_background_opacity' => '0.66',
                    'content_background_color' => '#f8f7f4',
                    'content_background_opacity' => '0.05',
                    'text_background_color' => '#f8f7f4',
                    'text_background_opacity' => '0.72',
                    'artwork_card_background_color' => '#f8f7f4',
                    'artwork_card_background_opacity' => '0.03',
                    'background_mode' => 'single',
                    'background_tile_size' => '360px',
                    'background_opacity' => '0.08',
                    'header_drop_shadow_enabled' => '1',
                    'header_drop_shadow' => '0 16px 40px rgba(0,0,0,0.18)',
                ],
            ],

            [
                'name' => 'Midnight Olive',
                'description' => 'Dark olive shell, parchment type, brass accent.',
                'tone' => 'dark botanical',
                'button_background' => '#1d2418',
                'button_text' => '#f2ead8',
                'button_accent' => '#c3a45b',
                'swatches' => ['#1d2418', '#f2ead8', '#c3a45b', '#37452b'],
                'values' => [
                    'primary_color' => '#f2ead8',
                    'accent_color' => '#c3a45b',
                    'text_color' => '#f2ead8',
                    'background_color' => '#1d2418',
                    'topbar_background_color' => '#1d2418',
                    'topbar_text_color' => '#f2ead8',
                    'topbar_background_opacity' => '0.92',
                    'menu_background_color' => '#37452b',
                    'menu_text_color' => '#f2ead8',
                    'menu_background_enabled' => '1',
                    'menu_background_opacity' => '0.9',
                    'heading_background_color' => '#37452b',
                    'heading_background_opacity' => '0.78',
                    'content_background_color' => '#1d2418',
                    'content_background_opacity' => '0.12',
                    'text_background_color' => '#37452b',
                    'text_background_opacity' => '0.72',
                    'artwork_card_background_color' => '#26301f',
                    'artwork_card_background_opacity' => '0.1',
                    'background_mode' => 'single',
                    'background_tile_size' => '360px',
                    'background_opacity' => '0.1',
                    'header_drop_shadow_enabled' => '1',
                    'header_drop_shadow' => '0 18px 52px rgba(0,0,0,0.44)',
                ],
            ],
            [
                'name' => 'Ultraviolet Paper',
                'description' => 'Soft lavender page, aubergine type, electric violet accent.',
                'tone' => 'cool luminous',
                'button_background' => '#f0e8ff',
                'button_text' => '#26153d',
                'button_accent' => '#7b4dff',
                'swatches' => ['#f0e8ff', '#26153d', '#7b4dff', '#d8c9ff'],
                'values' => [
                    'primary_color' => '#26153d',
                    'accent_color' => '#7b4dff',
                    'text_color' => '#26153d',
                    'background_color' => '#f0e8ff',
                    'topbar_background_color' => '#e3d5ff',
                    'topbar_text_color' => '#26153d',
                    'topbar_background_opacity' => '0.9',
                    'menu_background_color' => '#e3d5ff',
                    'menu_text_color' => '#26153d',
                    'menu_background_enabled' => '1',
                    'menu_background_opacity' => '0.84',
                    'heading_background_color' => '#fbf8ff',
                    'heading_background_opacity' => '0.7',
                    'content_background_color' => '#fbf8ff',
                    'content_background_opacity' => '0.06',
                    'text_background_color' => '#fbf8ff',
                    'text_background_opacity' => '0.72',
                    'artwork_card_background_color' => '#fbf8ff',
                    'artwork_card_background_opacity' => '0.03',
                    'background_mode' => 'single',
                    'background_tile_size' => '360px',
                    'background_opacity' => '0.08',
                    'header_drop_shadow_enabled' => '1',
                    'header_drop_shadow' => '0 16px 44px rgba(38,21,61,0.2)',
                ],
            ],
        ];
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
        return TenantAdminLayout::escape($value);
    }
}

// End of file.
