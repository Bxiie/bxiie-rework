<?php

declare(strict_types=1);

namespace App\Http\View;

/**
 * Shared platform admin shell.
 *
 * This layout is platform-only. Tenant admin pages must use TenantAdminLayout
 * so platform operations and tenant operations do not visually or navigationally
 * bleed into each other.
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
        $csrf = self::escape($_SESSION['csrf_token'] ?? '');

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
<body class="tenant-admin-page platform-admin-page">
<header class="site-header tenant-admin-public-header platform-admin-header">
    <a class="platform-admin-logo" href="/admin"><img src="/assets/logo_2.png" alt="ArtsFolio"></a>
    <div class="platform-admin-context">
        <strong>Platform Admin</strong>
        <span>Global ArtsFolio operations, not a tenant site</span>
    </div>
    <nav aria-label="Platform admin actions">
        <a href="/admin">Platform dashboard</a>
        <a href="/help/developer">Developer reference</a>
        <form method="post" action="/logout" class="inline-form"><input type="hidden" name="csrf_token" value="{$csrf}"><button type="submit" class="link-button">Log out</button></form>
    </nav>
</header>
<div class="tenant-admin-shell platform-admin-shell">
    <aside class="tenant-admin-sidebar platform-admin-sidebar" aria-label="Platform admin navigation">
        <div class="tenant-admin-sidebar-title"><strong>Platform</strong><span>System controls</span></div>
        {$adminNav}
    </aside>
    <main class="tenant-admin-main">
        <section class="tenant-admin-panel platform-admin-panel"><h1>{$safeTitle}</h1>{$body}</section>
    </main>
</div>
<footer class="site-footer tenant-admin-footer platform-admin-footer">
    <span>© {$year} artsfol.io platform administration</span>
    <nav><a href="/admin">Platform Admin</a><a href="/admin/platform-settings">Platform Settings</a><a href="/admin/routes">Routes</a></nav>
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
            'stats' => ['/admin/stats', 'Platform Stats'],
            'audit' => ['/admin/audit-log', 'Platform Audit Log'],
            'routes' => ['/admin/routes', 'Platform Routes'],
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
