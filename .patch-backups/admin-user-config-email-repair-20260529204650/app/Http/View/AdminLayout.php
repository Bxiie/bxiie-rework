<?php

declare(strict_types=1);

namespace App\Http\View;

use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;
use App\Tenant\Settings\TenantSettingsRepository;
use Throwable;

/**
 * Platform admin shell.
 *
 * This class is intentionally platform-specific. For safety during the
 * admin-shell refactor, render() detects tenant-host /admin requests and
 * delegates them to TenantAdminLayout. That prevents older tenant controllers
 * that still import AdminLayout from leaking platform navigation into tenant
 * pages while those controllers are gradually cleaned up.
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
        $active = array_key_exists('active', $args) ? (string) $args['active'] : (string) ($args[2] ?? ($args['nav'] ?? 'dashboard'));

        $tenantHtml = self::tenantFallback($title, $body, $active);
        if ($tenantHtml !== null) {
            return $tenantHtml;
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
    <link rel="stylesheet" href="/assets/site.css">
    <link rel="stylesheet" href="/assets/platform.css">
    <link rel="stylesheet" href="/assets/platform-custom.css">
    <link rel="stylesheet" href="/assets/tenant-admin.css?v=20260630-content-colors-bg-image-picker-layout">
    <link rel="stylesheet" href="/assets/admin-shell-refactor.css">
</head>
<body class="platform-admin-page">
<header class="platform-admin-topbar" aria-label="Platform admin header">
    <a class="platform-admin-logo" href="/platform/admin" aria-label="ArtsFolio platform admin"><img src="/assets/logo_2.png" alt="ArtsFolio"></a>
    <div class="platform-admin-identity"><strong>Platform Admin</strong><span>Global ArtsFolio operations, not a tenant site</span></div>
    <nav>
        <a href="/">Platform home</a>
        <a href="/pricing">Pricing</a>
        <a href="/directory">Directory</a>
        <a href="/help">Help</a>
        <a href="/help/developer">Developer Reference</a>
        <form method="post" action="/logout"><button type="submit">Log out</button></form>
    </nav>
</header>
<div class="platform-admin-shell">
    <aside class="platform-admin-sidebar" aria-label="Platform admin navigation">
        <div class="platform-admin-sidebar-title"><strong>ArtsFolio</strong><span>Platform Operations</span></div>
        {$adminNav}
    </aside>
    <main class="platform-admin-main">
        <section class="platform-admin-panel"><h1>{$safeTitle}</h1>{$body}</section>
    </main>
</div>
<footer class="site-footer platform-admin-footer">
    <span>© {$year} artsfol.io platform operations</span>
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

    private static function tenantFallback(string $title, string $body, string $active): ?string
    {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (!str_starts_with($path, '/admin')) {
            return null;
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') {
            return null;
        }

        try {
            $root = dirname(__DIR__, 3);
            $pdo = Database::connect($root);
            $tenant = (new TenantResolver($pdo))->resolveFromHost($host);
            if ($tenant === null) {
                return null;
            }

            return (new TenantAdminLayout(new TenantSettingsRepository($pdo)))->render(
                $tenant,
                $title,
                $body,
                self::tenantActive($active, $title, $path)
            );
        } catch (Throwable) {
            return null;
        }
    }

    private static function tenantActive(string $active, string $title, string $path): string
    {
        if ($active !== '' && $active !== 'dashboard') {
            return $active;
        }

        return match (true) {
            str_contains($path, '/admin/artworks') => 'artworks',
            str_contains($path, '/admin/content') => 'content',
            str_contains($path, '/admin/events') => 'events',
            str_contains($path, '/admin/contact-messages') => 'messages',
            str_contains($path, '/admin/email-signups') => 'email',
            str_contains($path, '/admin/billing') => 'billing',
            str_contains($path, '/admin/directory') || str_contains($path, '/admin/platform-discovery') => 'directory',
            str_contains($path, '/admin/stats') => 'stats',
            str_contains($path, '/admin/audit-log') => 'audit',
            str_contains($path, '/admin/settings') => 'settings',
            str_contains($path, '/admin/portfolio-sections') => 'sections',
            str_contains($path, '/admin/routes') => 'routes',
            str_contains(strtolower($title), 'artwork') => 'artworks',
            str_contains(strtolower($title), 'event') => 'events',
            str_contains(strtolower($title), 'billing') => 'billing',
            str_contains(strtolower($title), 'directory') => 'directory',
            str_contains(strtolower($title), 'stat') => 'stats',
            str_contains(strtolower($title), 'audit') => 'audit',
            default => 'dashboard',
        };
    }

    private static function adminNav(string $active): string
    {
        $items = [
            'dashboard' => ['/platform/admin', 'Dashboard'],
            'tenants' => ['/platform/admin/tenants', 'Tenants'],
            'domains' => ['/platform/admin/domains', 'Domains'],
            'pricing' => ['/platform/admin/pricing', 'Plans & Billing'],
            'jobs' => ['/platform/admin/jobs', 'Jobs'],
            'workers' => ['/platform/admin/workers', 'Workers'],
            'email' => ['/platform/admin/email-outbox', 'Email Outbox'],
            'stats' => ['/platform/admin/stats', 'Platform Stats'],
            'audit' => ['/platform/admin/audit-log', 'Platform Audit Log'],
            'routes' => ['/platform/admin/routes', 'Platform Routes'],
            'settings' => ['/platform/admin/platform-settings', 'Platform Settings'],
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
