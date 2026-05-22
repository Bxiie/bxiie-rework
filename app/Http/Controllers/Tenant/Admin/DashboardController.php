<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;


use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Membership\Roles;
use App\Platform\Tenancy\TenantContext;

/**
 * Handles tenant admin dashboard placeholder routes.
 */
final class DashboardController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
    ) {
    }

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $cards = [
            ['Site settings', '/admin/settings', 'Titles, tabs, slugs, colors, home intro, exhibition display, and tenant CSS.'],
            ['Content', '/admin/content', 'About/contact HTML, social links, page images, and public page copy.'],
            ['Artworks', '/admin/artworks', 'Review, filter, publish, edit, archive, and manage artwork metadata.'],
            ['Upload artwork', '/admin/artwork/upload', 'Add new artwork and media files.'],
            ['Portfolio sections', '/admin/portfolio-sections', 'Create sections, show tabs, and control tab ordering.'],
            ['Events / exhibitions', '/admin/events', 'Edit exhibition history and public About page event display.'],
            ['Contact messages', '/admin/contact-messages', 'Review and delete inbound public contact messages.'],
            ['Email signups', '/admin/email-signups', 'Review, import, export, and manage email-list subscribers.'],
            ['Stats', '/admin/stats', 'Traffic, artwork views, location rollups, and engagement analytics.'],
            ['Audit log', '/admin/audit-log', 'Review tenant admin activity and security-relevant changes.'],
        ];

        $html = '<section class="admin-hero"><h1>Tenant Admin</h1><p>Manage the public site, artwork catalog, engagement, and reporting.</p></section>';
        $html .= '<section class="admin-card-grid">';

        foreach ($cards as [$title, $href, $description]) {
            $title = AdminLayout::escape($title);
            $href = AdminLayout::escape($href);
            $description = AdminLayout::escape($description);
            $html .= "<a class=\"admin-card\" href=\"{$href}\"><strong>{$title}</strong><span>{$description}</span></a>";
        }

        $html .= '</section>';

        return AdminLayout::render(
            title: 'Tenant Admin',
            body: $html,
        );
    }
}

// End of file.
