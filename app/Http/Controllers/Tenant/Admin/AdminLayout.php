<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

final class AdminLayout
{
    public static function render(...$args): string
    {
        $title = array_key_exists('title', $args) ? (string) $args['title'] : (string) ($args[0] ?? 'Admin');
        $body = array_key_exists('body', $args) ? (string) ($args['body'] ?? '') : (string) ($args[1] ?? '');
        if ($body === '' && array_key_exists('content', $args)) {
            $body = (string) ($args['content'] ?? '');
        }
        if ($body === '' && array_key_exists('html', $args)) {
            $body = (string) ($args['html'] ?? '');
        }
        $active = array_key_exists('active', $args) ? (string) $args['active'] : (string) ($args['nav'] ?? 'dashboard');

        return self::renderShell($title, $body, $active);
    }

    public static function renderShell(string $title, string $body, string $active = 'dashboard'): string
    {
        $safeTitle = self::escape($title);
        $adminNav = self::adminNav($active);
        $year = date('Y');

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$safeTitle} | Bxiie</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/site.css">
    <link rel="stylesheet" href="/assets/tenant-admin.css">
</head>
<body class="tenant-admin-page" style="--tenant-topbar-bg:#f7f2e8;--tenant-topbar-text:#111;">
<header class="site-header tenant-admin-public-header">
    <a class="brand" href="/">Bxiie</a>
    <nav>
        <a href="/">Home</a>
        <a href="/portfolio">Portfolio</a>
        <a href="/about">About</a>
        <a href="/contact">Contact</a>
    </nav>
</header>
<div class="tenant-admin-shell">
    <aside class="tenant-admin-sidebar" aria-label="Tenant admin navigation">
        <div class="tenant-admin-sidebar-title"><strong>Admin</strong><span>Bxiie</span></div>
        {$adminNav}
    </aside>
    <main class="tenant-admin-main">
        <div class="tenant-admin-main-header"></div>
        <section class="tenant-admin-panel"><h1>{$safeTitle}</h1>{$body}</section>
    </main>
</div>
<footer class="site-footer tenant-admin-footer">
    <span>© {$year} Bxiie</span>
    <nav><a href="/help">Help</a><a href="/privacy">Privacy</a><a href="https://artsfol.io/contact">Contact artsfol.io</a></nav>
</footer>
</body>
</html>
HTML;
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private static function adminNav(string $active): string
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
}

// End of file.
