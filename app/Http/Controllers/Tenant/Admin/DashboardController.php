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

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, [Roles::TENANT_OWNER, Roles::TENANT_ADMIN, Roles::TENANT_EDITOR])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $email = AdminLayout::escape((string) ($currentUser['email'] ?? ''));
        $tenantName = AdminLayout::escape($tenant->name);
        $tenantSlug = AdminLayout::escape($tenant->slug);

        $body = <<<HTML
<p class="admin-muted">Tenant: {$tenantName} ({$tenantSlug})</p>
<p class="admin-muted">Signed in as {$email}</p>

<ul>
    <li><a href="/admin/settings">Client settings</a></li>
    <li>Artwork: coming soon</li>
    <li>Portfolio sections: coming soon</li>
    <li><a href="/admin/contact-messages">Contact messages</a></li>
    <li><a href="/admin/email-signups">Email signups</a></li>
    <li><a href="/admin/audit-log">Audit log</a></li>
    <li>Account and billing details: coming later</li>
    <li><a href="/admin/artworks">Artworks</a></li>
</ul>
HTML;

        return Response::html(AdminLayout::render(
            title: 'Tenant Admin',
            body: $body,
            nav: [
                '/admin' => 'Dashboard',
                '/admin/settings' => 'Settings',
                '/admin/contact-messages' => 'Contact Messages',
                '/admin/email-signups' => 'Email Signups',
                '/admin/audit-log' => 'Audit Log',
                '/admin/routes' => 'Routes',
            ],
        ));
    }
}

// End of file.
