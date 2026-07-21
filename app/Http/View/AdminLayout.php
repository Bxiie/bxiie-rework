<?php

declare(strict_types=1);

namespace App\Http\View;

use App\Platform\Membership\MembershipRepository;
use App\Platform\Workers\WorkerHeartbeatRepository;
use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Settings\TenantSettingsRepository;
use Throwable;

/**
 * Platform admin shell.
 *
 * This class is intentionally platform-specific. Tenant-host /admin requests
 * are delegated to TenantAdminLayout to prevent platform navigation from
 * leaking into tenant pages while legacy controllers are retired.
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
        if (is_array($active)) {
            $active = 'dashboard';
        }

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
        $csrf = self::escape(self::csrfToken());
        $identity = self::platformIdentity();
        $platformAdminLink = \App\Http\View\PlatformChrome::platformAdminLink();
        $canonicalNav = \App\Http\View\PlatformChrome::topNavigation('platform');
        $platformCopyright = \App\Http\View\PlatformChrome::copyrightLine();
        $workerWarning = self::workerHealthWarning();

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$safeTitle} | ArtsFolio Platform Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/site.css">
    <link rel="stylesheet" href="/assets/platform.css?v=20260708-logo-aspect">
    <link rel="stylesheet" href="/assets/platform-custom.css">
    <link rel="stylesheet" href="/assets/tenant-admin.css?v=20260708-logo-aspect">
    <script defer src="/assets/admin-color-fields.js?v=20260620-palette-contrast"></script>
    <link rel="stylesheet" href="/assets/admin-shell-refactor.css?v=20260623-email-outbox-containment">
    <script defer src="/assets/admin-typography-fields.js?v=20260620-typography-live"></script>
    <script defer src="/assets/admin-table-tools.js?v=20260623-logo-list-tools"></script>
</head>
<body class="platform-admin-page">
<header class="platform-admin-topbar" aria-label="Platform admin header">
    <a class="platform-admin-logo platform-admin-wordmark-card" href="/platform/admin" aria-label="ArtsFol.io platform admin"><img src="/assets/artsfol-wordmark.png?v=20260721-admin-visible-v2" alt="ArtsFol.io"></a>
    <div class="platform-admin-identity"><strong>Platform Admin</strong><span>Global ArtsFolio operations, not a tenant site</span><span>{$identity}</span></div>
    {$canonicalNav}
    <form method="post" action="/logout"><input type="hidden" name="csrf_token" value="{$csrf}"><button type="submit">Log out</button></form>
</header>
<div class="platform-admin-shell">
    <aside class="platform-admin-sidebar" aria-label="Platform admin navigation">
        <div class="platform-admin-sidebar-title"><strong>ArtsFolio</strong><span>Platform Operations</span></div>
        {$adminNav}
    </aside>
    <main class="platform-admin-main">
        <section class="platform-admin-panel"><h1>{$safeTitle}</h1>{$workerWarning}{$body}</section>
    </main>
</div>
<footer class="site-footer platform-admin-footer">
    <span>{$platformCopyright}</span>
    <nav><a href="/help">Help</a><a href="/account/timezone">Time zone</a><a href="/help/developer">Developer reference</a><a href="/privacy">Privacy</a><a href="/contact">Contact</a></nav>
</footer>
</body>
</html>
HTML;
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private static function csrfToken(): string
    {
        try {
            return (new CsrfTokenService())->getOrCreate();
        } catch (Throwable) {
            return '';
        }
    }


    /**
     * Shows a platform-admin-wide warning when the background worker heartbeat is
     * missing or older than the one-minute operational expectation.
     */
    private static function workerHealthWarning(): string
    {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (!str_starts_with($path, '/platform/admin') || $path === '/platform/admin/workers') {
            return '';
        }

        try {
            $pdo = Database::connect(dirname(__DIR__, 3));
            $heartbeats = new WorkerHeartbeatRepository($pdo);
            if ($heartbeats->hasHealthyWorker()) {
                return '';
            }

            $freshest = $heartbeats->freshestHeartbeat();
            $age = $freshest ? (string) $heartbeats->ageSeconds((string) $freshest['last_seen_at']) : 'none';

            return '<p class="admin-notice admin-notice-error"><strong>Background worker is not reporting.</strong> Last heartbeat age: ' . self::escape($age) . ' seconds. Check <code>artsfolio-background-worker.service</code>; queued jobs will not run until it is healthy.</p>';
        } catch (Throwable $e) {
            return '<p class="admin-notice admin-notice-error"><strong>Worker health check failed.</strong> ' . self::escape($e->getMessage()) . '</p>';
        }
    }

    private static function platformIdentity(): string
    {
        $currentUser = $GLOBALS['artsfolio_current_user'] ?? null;
        if (!is_array($currentUser)) {
            return 'Not signed in';
        }

        $email = self::escape((string) ($currentUser['email'] ?? 'Unknown user'));
        $name = self::escape((string) ($currentUser['display_name'] ?? ''));
        $roles = 'no platform role';

        try {
            $pdo = Database::connect(dirname(__DIR__, 3));
            $roleList = array_values(array_unique((new MembershipRepository($pdo))->platformRolesForUser((int) ($currentUser['user_id'] ?? 0))));
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
            str_contains($path, '/admin/users') => 'users',
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
            str_contains(strtolower($title), 'email template') => 'email_templates',
            str_contains(strtolower($title), 'audit') => 'audit',
            default => 'dashboard',
        };
    }


    /** Returns platform-wide counts for navigation items needing attention. */
    private static function platformAttentionCounts(): array
    {
        try {
            $pdo = Database::connect(dirname(__DIR__, 3));
            $messages = (int) $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE tenant_id IS NULL AND status = 'new'")->fetchColumn();
            $signups = (int) $pdo->query("SELECT COUNT(*) FROM email_signups WHERE created_at > COALESCE((SELECT setting_value FROM platform_settings WHERE setting_key = 'email_signups_last_viewed_at' LIMIT 1), '1970-01-01 00:00:00')")->fetchColumn();
            return ['contacts' => $messages, 'email_signups' => $signups];
        } catch (Throwable) {
            return [];
        }
    }

    private static function adminNav(string $active): string
    {
        $items = [
            'dashboard' => ['/platform/admin', 'Dashboard'],
            'tenants' => ['/platform/admin/tenants', 'Tenants'],
            'scale' => ['/platform/admin/scale-tenants', 'Scale Tenants'],
            'users' => ['/platform/admin/users', 'Users'],
            'codes' => ['/platform/admin/signup-codes', 'Signup Codes'],
            'domains' => ['/platform/admin/domains', 'Domains'],
            'pricing' => ['/platform/admin/pricing', 'Plans & Billing'],
            'billing_health' => ['/platform/admin/billing-health', 'Billing Health'],
            'billing_configuration' => ['/platform/admin/billing-configuration', 'Billing Config'],
            'sales' => ['/platform/admin/sales', 'Sales'],
            'sales_analytics' => ['/platform/admin/sales/analytics', 'Sales Analytics'],
            'jobs' => ['/platform/admin/jobs', 'Jobs'],
            'workers' => ['/platform/admin/workers', 'Workers'],
            'operations' => ['/platform/admin/operations', 'System Operations'],
            'backups' => ['/platform/admin/backups', 'Backups'],
            'contacts' => ['/platform/admin/contacts', 'Contacts'],
            'email_signups' => ['/platform/admin/email-signups', 'Email Signups'],
            'email' => ['/platform/admin/email-outbox', 'Email Outbox'],
            'email_templates' => ['/platform/admin/email-templates', 'Email Templates'],
            'stats' => ['/platform/admin/stats', 'Platform Stats'],
            'audit' => ['/platform/admin/audit-log', 'Platform Audit Log'],
            'routes' => ['/platform/admin/routes', 'Platform Routes'],
            'settings' => ['/platform/admin/platform-settings', 'Platform Settings'],
        ];

        $badges = self::platformAttentionCounts();
        $html = '<nav>';
        foreach ($items as $key => [$href, $label]) {
            $class = $active === $key ? ' class="active"' : '';
            $badge = isset($badges[$key]) && $badges[$key] > 0 ? '<span class="tenant-nav-badge" aria-label="' . $badges[$key] . ' new">' . ($badges[$key] > 99 ? '99+' : $badges[$key]) . '</span>' : '';
            $html .= '<a' . $class . ' href="' . self::escape($href) . '">' . self::escape($label) . $badge . '</a>';
        }
        $html .= '</nav>';

        return $html;
    }
}

// End of file.
