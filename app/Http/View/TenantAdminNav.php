<?php

declare(strict_types=1);

namespace App\Http\View;

use App\Support\Database;
use Throwable;

/**
 * Single source of truth for tenant-admin navigation.
 */
final class TenantAdminNav
{
    /**
     * Renders the tenant-admin navigation menu with the active item marked.
     */
    public static function render(string $active): string
    {
        $items = [
            'dashboard' => ['/admin', 'Dashboard'],
            'settings' => ['/admin/settings', 'Settings'],
            'content' => ['/admin/content', 'Content'],
            'artworks' => ['/admin/artworks', 'Artworks'],
            'curation' => ['/admin/curation', 'Curation'],
            'sections' => ['/admin/portfolio-sections', 'Portfolio Sections'],
            'events' => ['/admin/events', 'Events'],
            'messages' => ['/admin/contact-messages', 'Messages'],
            'email' => ['/admin/email-signups', 'Email Signups'],
            'domains' => ['/admin/domains', 'Domains'],
            'billing' => ['/admin/billing', 'Billing'],
            'sales' => ['/admin/sales', 'Sales'],
            'sales_analytics' => ['/admin/sales/analytics', 'Sales Analytics'],
            'users' => ['/admin/users', 'Users'],
            'stats' => ['/admin/stats', 'Stats'],
            'audit' => ['/admin/audit-log', 'Audit Log'],
            'routes' => ['/admin/routes', 'Tenant Routes'],
        ];

        $badges = self::newItemCounts();
        $html = '<nav>';
        foreach ($items as $key => [$href, $label]) {
            $class = $active === $key ? ' class="active"' : '';
            $badge = isset($badges[$key]) && $badges[$key] > 0 ? '<span class="tenant-nav-badge" aria-label="' . $badges[$key] . ' new">' . ($badges[$key] > 99 ? '99+' : $badges[$key]) . '</span>' : '';
            $html .= '<a' . $class . ' href="' . self::escape($href) . '">' . self::escape($label) . $badge . '</a>';
        }
        $html .= '</nav>';

        return $html;
    }

    /** Returns tenant-scoped counts for navigation items that need attention. */
    private static function newItemCounts(): array
    {
        $tenant = $GLOBALS['artsfolio_tenant'] ?? null;
        if (!is_object($tenant) || !isset($tenant->tenantId)) {
            return [];
        }

        try {
            $pdo = Database::connect(dirname(__DIR__, 3));
            $message = $pdo->prepare("SELECT COUNT(*) FROM contact_messages WHERE tenant_id = :tenant_id AND status = 'new'");
            $message->execute(['tenant_id' => (int) $tenant->tenantId]);
            $signup = $pdo->prepare("SELECT COUNT(*) FROM email_signups WHERE tenant_id = :tenant_id AND created_at > COALESCE((SELECT setting_value FROM tenant_settings WHERE tenant_id = :tenant_id AND setting_key = 'email_signups_last_viewed_at' LIMIT 1), '1970-01-01 00:00:00')");
            $signup->execute(['tenant_id' => (int) $tenant->tenantId]);

            return ['messages' => (int) $message->fetchColumn(), 'email' => (int) $signup->fetchColumn()];
        } catch (Throwable) {
            return [];
        }
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
