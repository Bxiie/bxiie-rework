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
 * Shows the current tenant-admin route map.
 */
final class RoutesController
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

        $routes = [
            ['GET', '/admin', 'Tenant dashboard'],
            ['GET', '/admin/settings', 'Tenant settings form'],
            ['POST', '/admin/settings', 'Save tenant settings'],
            ['GET', '/admin/contact-messages', 'Contact message list'],
            ['GET', '/admin/contact-messages.csv', 'Contact message CSV export'],
            ['POST', '/admin/contact-messages/status', 'Update contact message status'],
            ['GET', '/admin/email-signups', 'Email signup list'],
            ['GET', '/admin/email-signups.csv', 'Email signup CSV export'],
            ['POST', '/admin/email-signups/consent', 'Update email signup consent'],
            ['GET', '/admin/audit-log', 'Tenant audit log'],
            ['GET', '/admin/audit-log.csv', 'Tenant audit log CSV export'],
            ['GET', '/admin/routes', 'Tenant route map'],
        ];

        $rows = '';

        foreach ($routes as [$method, $path, $description]) {
            $rows .= '<tr>'
                . '<td>' . AdminLayout::escape($method) . '</td>'
                . '<td><code>' . AdminLayout::escape($path) . '</code></td>'
                . '<td>' . AdminLayout::escape($description) . '</td>'
                . '</tr>';
        }

        return Response::html(AdminLayout::render(
            title: 'Tenant Admin Routes',
            body: <<<HTML
<table class="admin-table">
    <thead>
        <tr>
            <th>Method</th>
            <th>Route</th>
            <th>Description</th>
        </tr>
    </thead>
    <tbody>
        {$rows}
    </tbody>
</table>
HTML,
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
