<?php

declare(strict_types=1);

namespace App\Http\View;

/**
 * Platform admin shell. Tenant admin pages should use TenantAdminLayout.
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
        if (is_array($active)) {
            $active = '';
        }

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
    <link rel="stylesheet" href="/assets/platform.css">
    <link rel="stylesheet" href="/assets/tenant-admin.css?v=20260630-content-colors-bg-image-picker-layout">
</head>
<body class="tenant-admin-page platform-admin-page">
<header class="site-header tenant-admin-public-header">
    <a class="brand" href="/admin"><img src="/assets/logo_2.png" alt="ArtsFolio" style="height:2rem;vertical-align:middle"></a>
    <nav><a href="/">Home</a><a href="/pricing">Pricing</a><a href="/directory">Directory</a><a href="/help">Help</a></nav>
</header>
<div class="tenant-admin-shell">
    <aside class="tenant-admin-sidebar" aria-label="Platform admin navigation">
        <div class="tenant-admin-sidebar-title"><strong>Platform Admin</strong><span>ArtsFolio</span></div>
        {$adminNav}
    </aside>
    <main class="tenant-admin-main"><section class="tenant-admin-panel"><h1>{$safeTitle}</h1>{$body}</section></main>
</div>
<footer class="site-footer tenant-admin-footer"><span>© {$year} ArtsFolio</span><nav><a href="/help">Help</a><a href="/privacy">Privacy</a><a href="/contact">Contact</a></nav></footer>
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
            'pricing' => ['/admin/pricing', 'Pricing'],
            'settings' => ['/admin/platform-settings', 'Settings'],
            'stats' => ['/admin/stats', 'Stats'],
            'messages' => ['/admin/contact-messages', 'Messages'],
            'email' => ['/admin/email-outbox', 'Email Outbox'],
            'jobs' => ['/admin/jobs', 'Jobs'],
            'workers' => ['/admin/workers', 'Workers'],
            'audit' => ['/admin/audit-log', 'Audit Log'],
            'routes' => ['/admin/routes', 'Routes'],
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
