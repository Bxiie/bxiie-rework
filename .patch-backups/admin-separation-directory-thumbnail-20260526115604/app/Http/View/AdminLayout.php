<?php

declare(strict_types=1);

namespace App\Http\View;

/**
 * Shared platform admin shell.
 *
 * Platform admin intentionally uses ArtsFolio branding and platform-specific
 * navigation rather than tenant/Bxiie labels.
 */
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
    <title>{$safeTitle} | ArtsFolio Platform Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/site.css">
    <link rel="stylesheet" href="/assets/platform.css">
    <link rel="stylesheet" href="/assets/platform-custom.css">
    <link rel="stylesheet" href="/assets/tenant-admin.css">
</head>
<body class="tenant-admin-page platform-admin-page" style="--tenant-topbar-bg:#f7f1e8;--tenant-topbar-text:#151515;">
<header class="site-header tenant-admin-public-header platform-admin-header">
    <a class="platform-admin-logo" href="/admin"><img src="/assets/logo_2.png" alt="ArtsFolio"></a>
    <nav>
        <a href="/">Platform home</a>
        <a href="/pricing">Pricing</a>
        <a href="/directory">Directory</a>
        <a href="/help">Help</a>
        <form method="post" action="/logout"><button type="submit">Log out</button></form>
    </nav>
</header>
<div class="tenant-admin-shell">
    <aside class="tenant-admin-sidebar" aria-label="Platform admin navigation">
        <div class="tenant-admin-sidebar-title"><strong>Platform Admin</strong><span>ArtsFolio operations</span></div>
        {$adminNav}
    </aside>
    <main class="tenant-admin-main">
        <section class="tenant-admin-panel"><h1>{$safeTitle}</h1>{$body}</section>
    </main>
</div>
<footer class="site-footer tenant-admin-footer">
    <span>© {$year} artsfol.io</span>
    <nav><a href="/help">Help</a><a href="/help/developer">Developer reference</a><a href="/privacy">Privacy</a><a href="/contact">Contact</a></nav>
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
            'tenants' => ['/admin/tenants', 'Tenants'],
            'domains' => ['/admin/domains', 'Domains'],
            'jobs' => ['/admin/jobs', 'Jobs'],
            'workers' => ['/admin/workers', 'Workers'],
            'email' => ['/admin/email-outbox', 'Email Outbox'],
            'stats' => ['/admin/stats', 'Stats'],
            'audit' => ['/admin/audit-log', 'Audit Log'],
            'routes' => ['/admin/routes', 'Routes'],
            'settings' => ['/admin/platform-settings', 'Platform Settings'],
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
