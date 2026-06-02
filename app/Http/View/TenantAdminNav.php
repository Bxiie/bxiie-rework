<?php

declare(strict_types=1);

namespace App\Http\View;

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
            'sections' => ['/admin/portfolio-sections', 'Portfolio Sections'],
            'events' => ['/admin/events', 'Events'],
            'messages' => ['/admin/contact-messages', 'Messages'],
            'email' => ['/admin/email-signups', 'Email Signups'],
            'billing' => ['/admin/billing', 'Billing'],
            'sales' => ['/admin/sales', 'Sales'],
            'sales_analytics' => ['/admin/sales/analytics', 'Sales Analytics'],
            'users' => ['/admin/users', 'Users'],
            'directory' => ['/admin/directory', 'Directory'],
            'stats' => ['/admin/stats', 'Stats'],
            'audit' => ['/admin/audit-log', 'Audit Log'],
            'routes' => ['/admin/routes', 'Tenant Routes'],
        ];

        $html = '<nav>';
        foreach ($items as $key => [$href, $label]) {
            $class = $active === $key ? ' class="active"' : '';
            $html .= '<a' . $class . ' href="' . self::escape($href) . '">' . self::escape($label) . '</a>';
        }
        $html .= '</nav>';

        return $html;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
