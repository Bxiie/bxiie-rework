<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Domains\DomainAdminRepository;
use App\Platform\Domains\DomainAdminService;
use App\Platform\Membership\Roles;
use App\Support\Flash\FlashMessages;
use App\Support\Pagination\Pagination;
use App\Support\Security\CsrfTokenService;

/**
 * Handles platform-admin custom domain list and actions.
 */
final class DomainsController
{
    public function __construct(
        private readonly RequirePlatformRole $roles,
        private readonly DomainAdminRepository $domains,
        private readonly ?DomainAdminService $service = null,
        private readonly ?CsrfTokenService $csrf = null,
        private readonly ?AuditLogRepository $auditLog = null,
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
        $csrf = AdminLayout::escape($this->csrf?->getOrCreate() ?? '');

        $rows = '';

        foreach ($this->domains->latest($limit, $offset) as $domain) {
            $domainId = (int) $domain['id'];
            $actions = <<<HTML
<form class="admin-inline-form" method="post" action="/admin/domains/action">
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <input type="hidden" name="domain_id" value="{$domainId}">
    <input type="hidden" name="custom_domain_action" value="verify_dns">
    <button type="submit">Verify DNS</button>
</form>
<form class="admin-inline-form" method="post" action="/admin/domains/action">
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <input type="hidden" name="domain_id" value="{$domainId}">
    <input type="hidden" name="custom_domain_action" value="render_vhost">
    <button type="submit">Render vhost</button>
</form>
HTML;

            $rows .= '<tr>'
                . '<td>' . AdminLayout::escape((string) $domain['id']) . '</td>'
                . '<td>' . AdminLayout::escape((string) $domain['hostname']) . '</td>'
                . '<td>' . AdminLayout::escape((string) $domain['status']) . '</td>'
                . '<td>' . AdminLayout::escape((string) $domain['tenant_slug']) . '</td>'
                . '<td>' . AdminLayout::escape((string) $domain['tenant_name']) . '</td>'
                . '<td>' . AdminLayout::escape((string) $domain['created_at']) . '</td>'
                . '<td>' . AdminLayout::escape((string) ($domain['updated_at'] ?? '')) . '</td>'
                . '<td>' . $actions . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="8">No custom domains found.</td></tr>';
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
            <th>Actions</th>
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

    public function action(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN])) {
            return Response::html('<h1>Forbidden</h1><p>Platform admin access required.</p>', 403);
        }

        if (!$this->csrf || !$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }

        if (!$this->service) {
            return Response::html('<h1>Domain service unavailable</h1>', 500);
        }

        $domainId = (int) ($_POST['domain_id'] ?? 0);
        $action = (string) ($_POST['custom_domain_action'] ?? '');

        if ($domainId <= 0) {
            return Response::html('<h1>Invalid domain id</h1>', 422);
        }

        try {
            if ($action === 'verify_dns') {
                $jobId = $this->service->queueDnsVerification($domainId);
                FlashMessages::success("Queued DNS verification job {$jobId}.");
                $this->auditAction($request, $currentUser, 'platform.custom_domain.verify_dns_queued', (string) $domainId, ['job_id' => $jobId]);
            } elseif ($action === 'render_vhost') {
                $documentRoot = getenv('ARTSFOLIO_PUBLIC_ROOT') ?: '/var/www/artsfolio/public';
                $jobId = $this->service->queueVhostRender($domainId, $documentRoot);
                FlashMessages::success("Queued vhost render job {$jobId}.");
                $this->auditAction($request, $currentUser, 'platform.custom_domain.render_vhost_queued', (string) $domainId, ['job_id' => $jobId]);
            } else {
                return Response::html('<h1>Invalid domain action</h1>', 422);
            }
        } catch (\Throwable $e) {
            FlashMessages::error('Domain action failed: ' . $e->getMessage());
        }

        return new Response('', 302, ['Location' => '/admin/domains']);
    }

    private function auditAction(Request $request, ?array $currentUser, string $action, string $entityId, array $details = []): void
    {
        if (!$this->auditLog) {
            return;
        }

        $this->auditLog->record(
            action: $action,
            userId: isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null,
            entityType: 'tenant_domain',
            entityId: $entityId,
            details: $details,
            ipAddress: $request->server('REMOTE_ADDR'),
        );
    }
}

// End of file.
