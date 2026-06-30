<?php

declare(strict_types=1);

namespace App\Http\View;

use App\Platform\Tenancy\TenantContext;
use App\Tenant\Settings\TenantSettingsRepository;

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

        $topbarBackground = self::escape($this->settings->get($tenant, 'topbar_background_color', '#f7f2e8'));
        $topbarText = self::escape($this->settings->get($tenant, 'topbar_text_color', '#111111'));
        $adminNav = TenantAdminNav::render($active);
        $csrf = self::escape($_SESSION['csrf_token'] ?? '');

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$browserTitle}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/site.css">
    <link rel="stylesheet" href="/assets/tenant-admin.css?v=20260630-content-colors-bg-image-picker-layout">
    <link rel="stylesheet" href="/assets/admin-shell-refactor.css">
</head>
<body class="tenant-admin-page" style="--tenant-topbar-bg: {$topbarBackground}; --tenant-topbar-text: {$topbarText};">
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
        <div class="tenant-admin-sidebar-title">
            <strong>{$siteTitle}</strong>
            <span>Tenant Admin · {$artistName}</span>
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

    private static function slug(string $value, string $fallback): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9-]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : $fallback;
    }
}

// End of file.
