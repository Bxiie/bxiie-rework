<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Membership\Roles;
use App\Platform\Tenants\TenantAdminRepository;

/**
 * Handles platform-admin tenant list screen.
 */
final class TenantsController
{
    public function __construct(
        private readonly RequirePlatformRole $roles,
        private readonly TenantAdminRepository $tenants,
    ) {
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT])) {
            return Response::html('<h1>Forbidden</h1><p>Platform admin access required.</p>', 403);
        }

        $rows = '';

        foreach ($this->tenants->latest() as $tenant) {
            $rows .= '<tr>'
                . '<td>' . $this->escape((string) $tenant['id']) . '</td>'
                . '<td>' . $this->escape((string) $tenant['slug']) . '</td>'
                . '<td>' . $this->escape((string) $tenant['name']) . '</td>'
                . '<td>' . $this->escape((string) $tenant['status']) . '</td>'
                . '<td>' . $this->escape((string) $tenant['domain_count']) . '</td>'
                . '<td>' . $this->escape((string) $tenant['created_at']) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6">No tenants found.</td></tr>';
        }

        return Response::html(AdminLayout::render(
            title: 'Tenants | Platform Admin',
            body: <<<HTML
<table class="admin-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Slug</th>
            <th>Name</th>
            <th>Status</th>
            <th>Domains</th>
            <th>Created</th>
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
                '/admin/email-outbox' => 'Email Outbox',
                '/admin/audit-log' => 'Audit Log',
                '/admin/platform-settings' => 'Settings',
            ],
        ));
    }

    private function escape(string $value): string
    {
        return AdminLayout::escape($value);
    }
}

// End of file.
