<?php

declare(strict_types=1);

namespace App\Http\View;

use App\Platform\Tenancy\TenantContext;
use App\Tenant\Settings\TenantSettingsRepository;

/**
 * Canonical tenant admin layout.
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
        $browserTitle = self::escape($title . ' | ' . $siteTitle);
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
        $adminNav = $this->adminNav($active);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$browserTitle}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/site.css">
    <link rel="stylesheet" href="/assets/tenant-admin.css">
</head>
<body class="tenant-admin-page" style="--tenant-topbar-bg: {$topbarBackground}; --tenant-topbar-text: {$topbarText};">
<header class="site-header tenant-admin-public-header">
    <a class="brand" href="/">{$siteTitle}</a>
    <nav>
        <a href="/">{$homeLabel}</a>
        <a href="/{$portfolioSlug}">{$portfolioLabel}</a>
        <a href="/{$aboutSlug}">{$aboutLabel}</a>
        <a href="/{$contactSlug}">{$contactLabel}</a>
    </nav>
</header>

<div class="tenant-admin-shell">
    <aside class="tenant-admin-sidebar" aria-label="Tenant admin navigation">
        <div class="tenant-admin-sidebar-title">
            <strong>Admin</strong>
            <span>{$artistName}</span>
        </div>
        {$adminNav}
    </aside>

    <main class="tenant-admin-main">
        <div class="tenant-admin-main-header">
            <a href="/admin">&larr; Admin</a>
            <a href="/">View public site</a>
        </div>
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

    private function adminNav(string $active): string
    {
        $items = [
            'dashboard' => ['/admin', 'Dashboard'],
            'settings' => ['/admin/settings', 'Settings'],
            'content' => ['/admin/content', 'Content'],
            'artworks' => ['/admin/artworks', 'Artworks'],
            'sections' => ['/admin/portfolio-sections', 'Portfolio Sections'],
            'events' => ['/admin/events', 'Events'],
            'messages' => ['/admin/contact-messages', 'Messages'],
            'email' => ['/admin/email-signups', 'Email Signups'],
            'discovery' => ['/admin/platform-discovery', 'Discovery'],
            'stats' => ['/admin/stats', 'Stats'],
            'audit' => ['/admin/audit-log', 'Audit Log'],
        ];

        $html = '<nav>';
        foreach ($items as $key => [$href, $label]) {
            $class = $active === $key ? ' class="active"' : '';
            $html .= '<a' . $class . ' href="' . self::escape($href) . '">' . self::escape($label) . '</a>';
        }
        $html .= '</nav>';

        return $html;
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
