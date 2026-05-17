<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Membership\Roles;

/**
 * Shows the current platform-admin route map.
 */
final class RoutesController
{
    public function __construct(
        private readonly RequirePlatformRole $roles,
    ) {
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT])) {
            return Response::html('<h1>Forbidden</h1><p>Platform admin access required.</p>', 403);
        }

        $routes = [
            ['GET', '/admin', 'Platform dashboard'],
            ['GET', '/admin/tenants', 'Tenant list'],
            ['GET', '/admin/domains', 'Custom domain list'],
            ['GET', '/admin/email-outbox', 'Email outbox'],
            ['GET', '/admin/audit-log', 'Audit log'],
            ['GET', '/admin/audit-log.csv', 'Audit log CSV export'],
            ['GET', '/admin/platform-settings', 'Platform settings form'],
            ['POST', '/admin/platform-settings', 'Save platform settings'],
            ['GET', '/admin/routes', 'Platform route map'],
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
            title: 'Platform Admin Routes',
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
                '/admin/tenants' => 'Tenants',
                '/admin/domains' => 'Domains',
                '/admin/email-outbox' => 'Email Outbox',
                '/admin/audit-log' => 'Audit Log',
                '/admin/platform-settings' => 'Settings',
                '/admin/routes' => 'Routes',
            ],
        ));
    }
}

// End of file.
