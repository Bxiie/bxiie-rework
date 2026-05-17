<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Jobs\JobAdminRepository;
use App\Platform\Membership\Roles;
use App\Support\Pagination\Pagination;

/**
 * Handles platform-admin background job list screen.
 */
final class JobsController
{
    public function __construct(
        private readonly RequirePlatformRole $roles,
        private readonly JobAdminRepository $jobs,
    ) {
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT])) {
            return Response::html('<h1>Forbidden</h1><p>Platform admin access required.</p>', 403);
        }

        $status = trim((string) ($_GET['status'] ?? ''));
        $jobType = trim((string) ($_GET['job_type'] ?? ''));
        $page = Pagination::pageFromQuery($_GET['page'] ?? 1);
        $limit = Pagination::limitFromQuery($_GET['limit'] ?? 50);
        $offset = Pagination::offset($page, $limit);

        $rows = '';

        foreach ($this->jobs->latest(
            status: $status !== '' ? $status : null,
            jobType: $jobType !== '' ? $jobType : null,
            limit: $limit,
            offset: $offset,
        ) as $job) {
            $payloadPreview = mb_substr((string) ($job['payload'] ?? ''), 0, 180);
            $errorPreview = mb_substr((string) ($job['last_error'] ?? ''), 0, 180);

            $rows .= '<tr>'
                . '<td>' . AdminLayout::escape((string) $job['id']) . '</td>'
                . '<td>' . AdminLayout::escape((string) ($job['tenant_slug'] ?? $job['tenant_id'] ?? '')) . '</td>'
                . '<td>' . AdminLayout::escape((string) $job['job_type']) . '</td>'
                . '<td>' . AdminLayout::escape((string) $job['status']) . '</td>'
                . '<td>' . AdminLayout::escape((string) ($job['attempts'] ?? '')) . '</td>'
                . '<td><code>' . AdminLayout::escape($payloadPreview) . '</code></td>'
                . '<td>' . AdminLayout::escape($errorPreview) . '</td>'
                . '<td>' . AdminLayout::escape((string) $job['created_at']) . '</td>'
                . '<td>' . AdminLayout::escape((string) ($job['updated_at'] ?? '')) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="9">No background jobs found.</td></tr>';
        }

        $statusValue = AdminLayout::escape($status);
        $jobTypeValue = AdminLayout::escape($jobType);

        $query = [
            'status' => $status,
            'job_type' => $jobType,
            'limit' => $limit,
        ];

        $prevUrl = Pagination::previousPageUrl('/admin/jobs', $query, $page);
        $nextUrl = Pagination::nextPageUrl('/admin/jobs', $query, $page);
        $pager = '<p>'
            . ($prevUrl ? '<a class="admin-button" href="' . AdminLayout::escape($prevUrl) . '">Previous</a> ' : '')
            . '<span class="admin-muted">Page ' . $page . '</span> '
            . '<a class="admin-button" href="' . AdminLayout::escape($nextUrl) . '">Next</a>'
            . '</p>';

        return Response::html(AdminLayout::render(
            title: 'Background Jobs | Platform Admin',
            body: <<<HTML
<form class="admin-form" method="get" action="/admin/jobs">
    <p>
        <label>Status<br>
            <input type="text" name="status" value="{$statusValue}">
        </label>
    </p>
    <p>
        <label>Job type<br>
            <input type="text" name="job_type" value="{$jobTypeValue}">
        </label>
    </p>
    <button type="submit">Filter</button>
    <a class="admin-button" href="/admin/jobs">Clear</a>
</form>

<table class="admin-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Tenant</th>
            <th>Type</th>
            <th>Status</th>
            <th>Attempts</th>
            <th>Payload</th>
            <th>Last Error</th>
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
                '/admin/jobs' => 'Jobs',
                '/admin/email-outbox' => 'Email Outbox',
                '/admin/audit-log' => 'Audit Log',
                '/admin/platform-settings' => 'Settings',
                '/admin/routes' => 'Routes',
            ],
        ));
    }
}

// End of file.
