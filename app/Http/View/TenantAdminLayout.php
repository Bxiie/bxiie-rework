<?php

declare(strict_types=1);

namespace App\Http\View;

use App\Platform\Tenancy\TenantContext;
use App\Platform\Membership\MembershipRepository;
use App\Support\Database;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Settings\TenantSettingsRepository;
use Throwable;

/**
 * Canonical tenant admin layout.
 *
 * Tenant admin pages must only show tenant-scoped functions. Platform-control
 * links belong exclusively in App\Http\View\AdminLayout.
 */
final class TenantAdminLayout
{
    public function __construct(
        private readonly TenantSettingsRepository $settings,
    ) {
    }

    public function render(TenantContext $tenant, string $title, string $body, string $active = ''): string
    {
        $siteTitle = self::escape($this->settings->get($tenant, 'site_title', $tenant->name));
        $artistName = self::escape($this->settings->get($tenant, 'artist_name', $siteTitle));
        $browserTitle = self::escape($title . ' | ' . $siteTitle . ' Admin');
        $copyrightName = $this->settings->get($tenant, 'copyright_name', $siteTitle);
        $year = date('Y');

        $homeLabel = self::escape($this->settings->get($tenant, 'home_tab', 'Home'));
        $portfolioLabel = self::escape($this->settings->get($tenant, 'portfolio_tab', 'Portfolio'));
        $aboutLabel = self::escape($this->settings->get($tenant, 'about_tab', 'About'));
        $contactLabel = self::escape($this->settings->get($tenant, 'contact_tab', 'Contact'));

        $portfolioSlug = self::slug($this->settings->get($tenant, 'portfolio_slug', 'portfolio'), 'portfolio');
        $aboutSlug = self::slug($this->settings->get($tenant, 'about_slug', 'about'), 'about');
        $contactSlug = self::slug($this->settings->get($tenant, 'contact_slug', 'contact'), 'contact');

        $primaryColor = self::escape($this->settings->get($tenant, 'primary_color', '#111111'));
        $accentColor = self::escape($this->settings->get($tenant, 'accent_color', '#c9a85f'));
        $backgroundColor = self::escape($this->settings->get($tenant, 'background_color', '#f7f2e8'));
        $topbarBackground = self::escape($this->settings->get($tenant, 'topbar_background_color', '#f7f2e8'));
        $topbarText = self::escape($this->settings->get($tenant, 'topbar_text_color', '#111111'));
        $menuText = self::escape($this->settings->get($tenant, 'menu_text_color', $topbarText));
        $textColor = self::escape($this->settings->get($tenant, 'text_color', '#1f1a14'));
        $surfaceStyle = self::tenantSurfaceCssVariables($tenant, $this->settings);
        $backgroundStyle = self::backgroundCssVariables($tenant, $this->settings);
        $adminNav = TenantAdminNav::render($active);
        $csrf = self::escape(self::csrfToken());
        $identity = self::tenantIdentity($tenant);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$browserTitle}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/site.css">
    <link rel="stylesheet" href="/assets/tenant-admin.css?v=20260623-sidebar-upload-palette">
    <script defer src="/assets/admin-color-fields.js?v=20260620-palette-contrast"></script>
    <link rel="stylesheet" href="/assets/admin-shell-refactor.css">
    <script defer src="/assets/admin-typography-fields.js?v=20260620-typography-live"></script>
    <script defer src="/assets/admin-table-tools.js?v=20260623-logo-list-tools"></script>
</head>
<body class="tenant-admin-page" style="--primary: {$primaryColor}; --accent: {$accentColor}; --bg: {$backgroundColor}; --tenant-topbar-bg: {$topbarBackground}; --tenant-topbar-text: {$topbarText}; --menu-text-color: {$menuText}; --text-color: {$textColor}; {$backgroundStyle}{$surfaceStyle}">
<header class="site-header tenant-admin-public-header">
    <a class="brand tenant-admin-brand" href="/"><strong>{$siteTitle}</strong><span>Tenant Admin</span></a>
    <nav>
        <a href="/">{$homeLabel}</a>
        <a href="/{$portfolioSlug}">{$portfolioLabel}</a>
        <a href="/{$aboutSlug}">{$aboutLabel}</a>
        <a href="/{$contactSlug}">{$contactLabel}</a>
        <a href="/help">Help</a>
        <form method="post" action="/logout" style="display:inline"><input type="hidden" name="csrf_token" value="{$csrf}"><button type="submit" class="link-button">Log out</button></form>
    </nav>
</header>

<div class="tenant-admin-shell">
    <aside class="tenant-admin-sidebar" aria-label="Tenant admin navigation">
        <a class="tenant-admin-sidebar-upload" href="/admin/artwork/upload">
            <span aria-hidden="true">＋</span>
            <strong>Upload Artwork</strong>
        </a>
        <div class="tenant-admin-sidebar-title">
            <strong>{$siteTitle}</strong>
            <span>Tenant Admin · {$artistName}</span><span>{$identity}</span>
        </div>
        {$adminNav}
        <div class="tenant-admin-sidebar-foot"><a href="/" target="_blank" rel="noopener">View Site</a></div>
    </aside>

    <main class="tenant-admin-main">
        <section class="tenant-admin-panel">
            <h1>{$this->escape($title)}</h1>



            {$body}
        </section>
    </main>
</div>

<footer class="site-footer tenant-admin-footer">
    <span>© {$year} {$copyrightName}</span>
    <nav>
        <a href="/help">Help</a>
        <a href="/account/timezone">Time zone</a>
        <a href="/privacy">Privacy</a>
        <a href="https://artsfol.io/contact">Contact artsfol.io</a>
    </nav>
</footer>
</body>
</html>
HTML;
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }


    /**
     * Mirrors public tenant surface variables in admin so settings preview honestly.
     */
    private static function tenantSurfaceCssVariables(TenantContext $tenant, TenantSettingsRepository $settings): string
    {
        $vars = '';
        $headingColor = (string) $settings->get($tenant, 'heading_background_color', '#fff8ec');
        $headingOpacity = self::safeOpacity((string) $settings->get($tenant, 'heading_background_opacity', '0.78'));
        $contentColor = (string) $settings->get($tenant, 'content_background_color', '#fffaf0');
        $contentOpacity = self::safeOpacity((string) $settings->get($tenant, 'content_background_opacity', '0.76'));
        $textBgColor = (string) $settings->get($tenant, 'text_background_color', '#fff7e8');
        $textBgOpacity = self::safeOpacity((string) $settings->get($tenant, 'text_background_opacity', '0.72'));
        $menuColor = (string) $settings->get($tenant, 'menu_background_color', (string) $settings->get($tenant, 'topbar_background_color', '#fff8ec'));
        $menuOpacity = self::safeOpacity((string) $settings->get($tenant, 'menu_background_opacity', '0.86'));
        $menuEnabled = (string) $settings->get($tenant, 'menu_background_enabled', '1') !== '0' && (float) $menuOpacity > 0.0;
        $cardColor = (string) $settings->get($tenant, 'artwork_card_background_color', '#fffaf0');
        $cardOpacity = self::safeOpacity((string) $settings->get($tenant, 'artwork_card_background_opacity', '0.84'));

        $vars .= '--heading-bg:' . self::safeCssColor($headingColor) . ';';
        $vars .= '--heading-bg-overlay:' . self::cssColorWithOpacity($headingColor, $headingOpacity) . ';';
        $vars .= '--heading-bg-opacity:' . $headingOpacity . ';';
        $vars .= '--content-bg:' . self::safeCssColor($contentColor) . ';';
        $vars .= '--content-bg-overlay:' . self::cssColorWithOpacity($contentColor, $contentOpacity) . ';';
        $vars .= '--content-bg-opacity:' . $contentOpacity . ';';
        $vars .= '--text-bg:' . self::safeCssColor($textBgColor) . ';';
        $vars .= '--text-bg-overlay:' . self::cssColorWithOpacity($textBgColor, $textBgOpacity) . ';';
        $vars .= '--text-bg-opacity:' . $textBgOpacity . ';';
        $vars .= '--menu-bg:' . self::safeCssColor($menuColor) . ';';
        $vars .= '--menu-bg-overlay:' . ($menuEnabled ? self::cssColorWithOpacity($menuColor, $menuOpacity) : 'transparent') . ';';
        $vars .= '--menu-bg-opacity:' . ($menuEnabled ? $menuOpacity : '0') . ';';
        $vars .= '--menu-panel-padding:' . ($menuEnabled ? '0.35rem 0.55rem' : '0') . ';';
        $vars .= '--menu-panel-radius:' . ($menuEnabled ? '999px' : '0') . ';';
        $vars .= '--menu-panel-shadow:' . ($menuEnabled ? '0 12px 32px rgba(0,0,0,0.08)' : 'none') . ';';
        $topbarColor = (string) $settings->get($tenant, 'topbar_background_color', '#fff8ec');
        $topbarOpacity = self::safeOpacity((string) $settings->get($tenant, 'topbar_background_opacity', '0.86'));
        $vars .= '--topbar-bg:' . self::safeCssColor($topbarColor) . ';';
        $vars .= '--tenant-topbar-bg:' . self::safeCssColor($topbarColor) . ';';
        $vars .= '--tenant-topbar-text:' . self::safeCssColor((string) $settings->get($tenant, 'topbar_text_color', (string) $settings->get($tenant, 'text_color', '#1f1a14'))) . ';';
        $vars .= '--menu-text-color:' . self::safeCssColor((string) $settings->get($tenant, 'menu_text_color', (string) $settings->get($tenant, 'topbar_text_color', (string) $settings->get($tenant, 'text_color', '#1f1a14')))) . ';';
        $vars .= '--topbar-bg-overlay:' . self::cssColorWithOpacity($topbarColor, $topbarOpacity) . ';';
        $vars .= '--topbar-bg-opacity:' . $topbarOpacity . ';';
        $vars .= '--tenant-header-shadow:' . ($settings->get($tenant, 'header_drop_shadow_enabled', '1') === '1' ? self::safeCssShadow((string) $settings->get($tenant, 'header_drop_shadow', '0 18px 45px rgba(0,0,0,0.24)')) : 'none') . ';';
        $vars .= '--artwork-card-bg:' . self::safeCssColor($cardColor) . ';';
        $vars .= '--artwork-card-bg-overlay:' . self::cssColorWithOpacity($cardColor, $cardOpacity) . ';';
        $vars .= '--artwork-card-bg-opacity:' . $cardOpacity . ';';
        $vars .= '--artwork-card-bg-size:' . self::safeCssSize((string) $settings->get($tenant, 'artwork_card_background_size', 'cover')) . ';';
        $vars .= $menuEnabled ? self::mediaVar((string) $settings->get($tenant, 'menu_media_uuid', ''), '--menu-bg-image', true) : '--menu-bg-image:none;';
        $vars .= self::mediaVar((string) $settings->get($tenant, 'topbar_media_uuid', ''), '--topbar-bg-image');
        $vars .= self::mediaVar((string) $settings->get($tenant, 'artwork_card_media_uuid', ''), '--artwork-card-bg-image');

        return $vars;
    }

    /**
     * Mirrors public tenant page background variables in tenant admin pages.
     */
    private static function backgroundCssVariables(TenantContext $tenant, TenantSettingsRepository $settings): string
    {
        $uuid = strtolower(trim((string) $settings->get($tenant, 'background_media_uuid', '')));
        if ($uuid === '' || !preg_match('/^[a-f0-9-]{36}$/', $uuid)) {
            return '';
        }

        $mode = (string) $settings->get($tenant, 'background_mode', 'single');
        $tileSize = self::safeCssSize((string) $settings->get($tenant, 'background_tile_size', '360px'));
        $opacity = self::safeOpacity((string) $settings->get($tenant, 'background_opacity', '0.12'));
        $imageUrl = '/media?uuid=' . rawurlencode($uuid);
        $repeat = $mode === 'tile' ? 'repeat' : 'no-repeat';
        $size = $mode === 'tile' ? $tileSize : 'cover';

        return "--site-bg-image:url('" . self::escape($imageUrl) . "');"
            . '--site-bg-repeat:' . $repeat . ';'
            . '--site-bg-size:' . self::escape($size) . ';'
            . '--site-bg-opacity:' . $opacity . ';';
    }

    private static function mediaVar(string $uuid, string $cssVar, bool $backgroundUsage = false): string
    {
        $uuid = strtolower(trim($uuid));
        if ($uuid === '' || !preg_match('/^[a-f0-9-]{36}$/', $uuid)) {
            return '';
        }

        $usage = $backgroundUsage ? '&usage=background' : '';

        return $cssVar . ":url('/media?uuid=" . rawurlencode($uuid) . $usage . "');";
    }

    /**
     * Converts common CSS colors to alpha-applied overlay values for admin previews.
     */
    private static function cssColorWithOpacity(string $color, string $opacity): string
    {
        $color = trim($color);
        $alpha = self::safeOpacity($opacity);

        if (preg_match('/^#([0-9a-fA-F]{6})$/', $color, $matches) === 1) {
            $hex = $matches[1];
            return sprintf('rgba(%d,%d,%d,%s)', hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)), $alpha);
        }

        if (preg_match('/^#([0-9a-fA-F]{3})$/', $color, $matches) === 1) {
            $hex = $matches[1];
            return sprintf('rgba(%d,%d,%d,%s)', hexdec(str_repeat($hex[0], 2)), hexdec(str_repeat($hex[1], 2)), hexdec(str_repeat($hex[2], 2)), $alpha);
        }

        if (preg_match('/^rgba?\(([^)]+)\)$/i', $color, $matches) === 1) {
            $parts = array_map('trim', explode(',', $matches[1]));
            if (count($parts) >= 3) {
                return 'rgba(' . $parts[0] . ',' . $parts[1] . ',' . $parts[2] . ',' . $alpha . ')';
            }
        }

        return self::safeCssColor($color);
    }

    /**
     * Restricts box-shadow settings to simple CSS tokens.
     */
    private static function safeCssShadow(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strtolower($value) === 'none') {
            return 'none';
        }

        return preg_match('/^[#a-zA-Z0-9.,%()\s-]+$/', $value) ? $value : '0 18px 45px rgba(0,0,0,0.24)';
    }

    /**
     * Restricts background-size values to simple CSS tokens.
     */
    private static function safeCssSize(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'cover';
        }

        return preg_match('/^[#a-zA-Z0-9.,%()\s-]+$/', $value) ? $value : 'cover';
    }

    private static function safeOpacity(string $value): string
    {
        $opacity = is_numeric($value) ? (float) $value : 0.72;
        $opacity = max(0.0, min(1.0, $opacity));

        return rtrim(rtrim(sprintf('%.2F', $opacity), '0'), '.');
    }

    private static function safeCssColor(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'rgba(255,255,255,0.72)';
        }

        return preg_match('/^[#a-zA-Z0-9.,%()\s-]+$/', $value) ? $value : 'rgba(255,255,255,0.72)';
    }

    private static function csrfToken(): string
    {
        try {
            return (new CsrfTokenService())->getOrCreate();
        } catch (Throwable) {
            return '';
        }
    }

    private static function tenantIdentity(TenantContext $tenant): string
    {
        $currentUser = $GLOBALS['artsfolio_current_user'] ?? null;
        if (!is_array($currentUser)) {
            return 'Not signed in';
        }

        $email = self::escape((string) ($currentUser['email'] ?? 'Unknown user'));
        $name = self::escape((string) ($currentUser['display_name'] ?? ''));
        $roles = 'no tenant role';

        try {
            $pdo = Database::connect(dirname(__DIR__, 3));
            $roleList = array_values(array_unique((new MembershipRepository($pdo))->tenantRolesForUser($tenant->tenantId, (int) ($currentUser['user_id'] ?? 0))));
            if ($roleList !== []) {
                $roles = implode(', ', $roleList);
            }
        } catch (Throwable) {
            $roles = 'role lookup unavailable';
        }

        $safeRoles = self::escape($roles);
        $namePart = $name !== '' ? "{$name} · " : '';

        return "Signed in as {$namePart}{$email} · {$safeRoles}";
    }

    private static function slug(string $value, string $fallback): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9-]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : $fallback;
    }
}

// End of file.
