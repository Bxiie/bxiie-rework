<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Http\View\ErrorPage;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Domains\DomainAdminRepository;
use App\Platform\Domains\DomainAdminService;
use App\Platform\Membership\Roles;
use App\Support\Flash\FlashMessages;
use App\Support\Pagination\Pagination;
use App\Support\Security\CsrfTokenService;

/**
 * Handles platform-admin custom-domain list and safe domain actions.
 *
 * ArtsFolio now uses Caddy on-demand TLS. DNS verification is still useful,
 * because it confirms the custom hostname points at this deployment before the
 * Caddy ask endpoint authorizes certificate issuance. Apache vhost rendering is
 * intentionally not exposed in the UI because it is obsolete for the Caddy
 * deployment model and can leave confusing stale artifacts behind.
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
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $page = Pagination::pageFromQuery($_GET['page'] ?? 1);
        $limit = Pagination::limitFromQuery($_GET['limit'] ?? 50);
        $offset = Pagination::offset($page, $limit);
        $csrf = AdminLayout::escape($this->csrf?->getOrCreate() ?? '');

        $rows = '';

        foreach ($this->domains->latest($limit, $offset) as $domain) {
            $domainId = (int) $domain['id'];
            $status = (string) $domain['status'];
            $actions = <<<HTML
<form class="admin-inline-form" method="post" action="/platform/admin/domains/action">
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <input type="hidden" name="domain_id" value="{$domainId}">
    <input type="hidden" name="custom_domain_action" value="verify_dns">
    <button type="submit">Verify DNS</button>
</form>
<form class="admin-inline-form" method="post" action="/platform/admin/domains/action" onsubmit="return confirm(&quot;Delete this domain?&quot;);">
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <input type="hidden" name="domain_id" value="{$domainId}">
    <input type="hidden" name="custom_domain_action" value="delete">
    <button type="submit">Delete</button>
</form>
HTML;
            $dnsResult = $this->dnsResultSummary($domain);

            $rows .= '<tr>'
                . '<td>' . AdminLayout::escape((string) $domain['id']) . '</td>'
                . '<td>' . AdminLayout::escape((string) $domain['hostname']) . '</td>'
                . '<td>' . AdminLayout::escape($status) . '</td>'
                . '<td>' . AdminLayout::escape((string) $domain['tenant_slug']) . '</td>'
                . '<td>' . AdminLayout::escape((string) $domain['tenant_name']) . '</td>'
                . '<td>' . AdminLayout::escape((string) $domain['created_at']) . '</td>'
                . '<td>' . AdminLayout::escape((string) ($domain['updated_at'] ?? '')) . '</td>'
                . '<td>' . $dnsResult . '</td>'
                . '<td>' . $actions . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="9">No custom domains found.</td></tr>'; 
        }

        $query = ['limit' => $limit];
        $prevUrl = Pagination::previousPageUrl('/platform/admin/domains', $query, $page);
        $nextUrl = Pagination::nextPageUrl('/platform/admin/domains', $query, $page);
        $notice = ((string) ($_GET['notice'] ?? '')) === 'domain-action-queued' ? '<p class="admin-notice admin-notice-success">Domain action queued. Check Jobs for execution details.</p>' : '';
        $pager = '<p>'
            . ($prevUrl ? '<a class="admin-button" href="' . AdminLayout::escape($prevUrl) . '">Previous</a> ' : '')
            . '<span class="admin-muted">Page ' . $page . '</span> '
            . '<a class="admin-button" href="' . AdminLayout::escape($nextUrl) . '">Next</a>'
            . '</p>';

        return Response::html(AdminLayout::render(
            title: 'Custom Domains | Platform Admin',
            body: <<<HTML
<section class="admin-card">
    <h2>Custom domains</h2>
    {$notice}
    <p>Verify DNS for custom domains. Verification queues a background job and returns a visible confirmation.</p>
    <form method="post" action="/platform/admin/domains/action" class="admin-form">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <input type="hidden" name="custom_domain_action" value="add">
        <label>Tenant ID or slug<input type="text" name="tenant_ref" placeholder="1 or bxiie" required></label>
        <label>Custom domain<input type="text" name="hostname" placeholder="example.com" required></label>
        <label><input type="checkbox" name="skip_plan_check" value="1"> Platform override plan limit</label>
        <button type="submit">Add custom domain</button>
    </form>
</section>
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
            <th>Last DNS Result</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        {$rows}
    </tbody>
</table>
{$pager}
HTML,
            active: 'domains',
        ));
    }

    public function action(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        if (!$this->csrf || !$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }

        if (!$this->service) {
            return Response::html('<h1>Domain service unavailable</h1>', 500);
        }

        $domainId = (int) ($_POST['domain_id'] ?? 0);
        $action = (string) ($_POST['custom_domain_action'] ?? '');

        try {
            if ($action === 'add') {
                $tenantId = $this->service->resolveTenantId((string) ($_POST['tenant_ref'] ?? $_POST['tenant_id'] ?? ''));
                $hostname = (string) ($_POST['hostname'] ?? '');
                $domainId = $this->service->addCustomDomain($tenantId, $hostname, isset($_POST['skip_plan_check']));
                FlashMessages::success("Custom domain {$domainId} added.");
                $this->auditAction($request, $currentUser, 'platform.custom_domain.added', (string) $domainId, ['tenant_id' => $tenantId, 'hostname' => $hostname]);
            } elseif ($action === 'delete') {
                if ($domainId <= 0) {
                    return Response::html('<h1>Invalid domain id</h1>', 422);
                }
                $this->service->deleteDomain($domainId);
                FlashMessages::success('Custom domain deleted.');
                $this->auditAction($request, $currentUser, 'platform.custom_domain.deleted', (string) $domainId);
            } elseif ($action === 'verify_dns') {
                if ($domainId <= 0) {
                    return Response::html('<h1>Invalid domain id</h1>', 422);
                }
                $jobId = $this->service->queueDnsVerification($domainId);
                FlashMessages::success("Queued DNS verification job {$jobId}. Caddy will serve the domain after verification marks it active.");
                $this->auditAction($request, $currentUser, 'platform.custom_domain.verify_dns_queued', (string) $domainId, ['job_id' => $jobId]);
            } elseif ($action === 'render_vhost') {
                FlashMessages::success('Render vhost is no longer required because ArtsFolio uses Caddy on-demand TLS. Verify DNS instead.');
                $this->auditAction($request, $currentUser, 'platform.custom_domain.render_vhost_skipped_caddy', (string) $domainId);
            } else {
                return Response::html('<h1>Invalid domain action</h1>', 422);
            }
        } catch (\Throwable $e) {
            FlashMessages::error('Domain action failed: ' . $e->getMessage());
        }

        return new Response('', 303, ['Location' => '/platform/admin/domains?notice=domain-action-queued']);
    }


    /**
     * Summarizes the most recent DNS verification result persisted by the worker.
     */
    private function dnsResultSummary(array $domain): string
    {
        $checkedAt = trim((string) ($domain['dns_last_checked_at'] ?? ''));
        $error = trim((string) ($domain['dns_last_error'] ?? ''));
        $raw = trim((string) ($domain['dns_last_result'] ?? ''));

        if ($checkedAt === '' && $raw === '' && $error === '') {
            return '<span class="admin-muted">Not checked yet</span>';
        }

        if ($error !== '') {
            return '<strong>Failed</strong><br><span class="admin-muted">' . AdminLayout::escape($checkedAt) . '</span><br><code>' . AdminLayout::escape($error) . '</code>';
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return '<span class="admin-muted">' . AdminLayout::escape($checkedAt) . '</span><br><code>' . AdminLayout::escape(mb_substr($raw, 0, 220)) . '</code>';
        }

        $verified = !empty($decoded['verified']) ? 'Verified' : 'Not verified';
        $actual = implode(', ', array_map('strval', $decoded['actual_ipv4'] ?? []));
        $expected = implode(', ', array_map('strval', $decoded['expected_ipv4'] ?? []));

        return '<strong>' . AdminLayout::escape($verified) . '</strong><br>'
            . '<span class="admin-muted">' . AdminLayout::escape($checkedAt) . '</span><br>'
            . '<small>Actual: ' . AdminLayout::escape($actual !== '' ? $actual : 'none') . '</small><br>'
            . '<small>Expected: ' . AdminLayout::escape($expected !== '' ? $expected : 'none configured') . '</small>';
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
