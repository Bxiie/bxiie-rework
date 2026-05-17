<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Domains\DomainAdminRepository;
use App\Platform\Membership\Roles;
use App\Support\Pagination\Pagination;

/**
 * Handles platform-admin custom domain list screen.
 */
final class DomainsController
{
    public function __construct(
        private readonly RequirePlatformRole $roles,
        private readonly DomainAdminRepository $domains,
    ) {
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT])) {
            return Response::html('<h1>Forbidden</h1><p>Platform admin access required.</p>', 403);
        }

        $page = Pagination::pageFromQuery($_GET['page'] ?? 1);
        $limit = Pagination::limitFromQuery($_GET['limit'] ?? 50);
        $offset = Pagination::offset($page, $limit);

        $rows = '';

        foreach ($this->domains->latest($limit, $offset) as $domain) {
            $rows .= '<tr>'
                . '<td>' . AdminLayout::escape((string) $domain['id']) . '</td>'
                . '<td>' . AdminLayout::escape((string) $domain['hostname']) . '</td>'
                . '<td>' . AdminLayout::escape((string) $domain['status']) . '</td>'
                . '<td>' . AdminLayout::escape((string) $domain['tenant_slug']) . '</td>'
                . '<td>' . AdminLayout::escape((string) $domain['tenant_name']) . '</td>'
                . '<td>' . AdminLayout::escape((string) $domain['created_at']) . '</td>'
                . '<td>' . AdminLayout::escape((string) ($domain['updated_at'] ?? '')) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="7">No custom domains found.</td></tr>';
        }

        $query = ['limit' => $limit];
        $prevUrl = Pagination::previousPageUrl('/admin/domains', $query, $page);
        $nextUrl = Pagination::nextPageUrl('/admin/domains', $query, $page);
        $pager = '<p>'
            . ($prevUrl ? '<a class="admin-button" href="' . AdminLayout::escape($prevUrl) . '">Previous</a> ' : '')
            . '<span class="admin-muted">Page ' . $page . '</span> '
            . '<a class="admin-button" href="' . AdminLayout::escape($nextUrl) . '">Next</a>'
            . '</p>';

        return Response::html(AdminLayout::render(
            title: 'Custom Domains | Platform Admin',
            body: <<<HTML
<table class="admin-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Hostname</th>
            <th>Status</th>
            <th>Tenant Slug</th>
            <th>Tenant Name</th>
            <th>Created</th>
            <th>Updated</th>
        </tr>
    </thead>
    <tbody>
        {$rows}
    </tbody>
</table>
{$pager}
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
