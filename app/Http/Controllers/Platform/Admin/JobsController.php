<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;


use App\Http\View\ErrorPage;
use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Jobs\JobAdminRepository;
use App\Platform\Jobs\JobAttemptRepository;
use App\Platform\Jobs\JobAdminService;
use App\Platform\Membership\Roles;
use App\Support\Flash\FlashMessages;
use App\Support\Pagination\Pagination;
use App\Support\Security\CsrfTokenService;

/**
 * Handles platform-admin background job list, detail, and maintenance actions.
 */
final class JobsController
{
    public function __construct(
        private readonly RequirePlatformRole $roles,
        private readonly JobAdminRepository $jobs,
        private readonly ?JobAdminService $service = null,
        private readonly ?CsrfTokenService $csrf = null,
        private readonly ?AuditLogRepository $auditLog = null,
        private readonly ?JobAttemptRepository $attempts = null,
    ) {
    }

    public function show(Request $request, ?array $currentUser, int $jobId): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $job = $this->jobs->find($jobId);

        if (!$job) {
            return Response::html('<h1>Job not found</h1>', 404);
        }

        $payload = json_decode((string) ($job['payload'] ?? ''), true);
        $payloadPretty = $payload === null
            ? (string) ($job['payload'] ?? '')
            : json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $attemptRows = '';

        foreach (($this->attempts?->forJob($jobId) ?? []) as $attempt) {
            $attemptRows .= '<tr>'
                . '<td>' . AdminLayout::escape((string) $attempt['id']) . '</td>'
                . '<td>' . AdminLayout::escape((string) $attempt['status']) . '</td>'
                . '<td>' . AdminLayout::escape((string) ($attempt['message'] ?? '')) . '</td>'
                . '<td>' . AdminLayout::escape((string) ($attempt['started_at'] ?? '')) . '</td>'
                . '<td>' . AdminLayout::escape((string) ($attempt['finished_at'] ?? '')) . '</td>'
                . '<td>' . AdminLayout::escape((string) $attempt['created_at']) . '</td>'
                . '</tr>';
        }

        if ($attemptRows === '') {
            $attemptRows = '<tr><td colspan="6">No attempt history found.</td></tr>';
        }

        $body = '<dl>'
            . '<dt>ID</dt><dd>' . AdminLayout::escape((string) $job['id']) . '</dd>'
            . '<dt>Tenant</dt><dd>' . AdminLayout::escape((string) ($job['tenant_slug'] ?? $job['tenant_id'] ?? '')) . '</dd>'
            . '<dt>Type</dt><dd>' . AdminLayout::escape((string) $job['job_type']) . '</dd>'
            . '<dt>Status</dt><dd>' . AdminLayout::escape((string) $job['status']) . '</dd>'
            . '<dt>Attempts</dt><dd>' . AdminLayout::escape((string) ($job['attempts'] ?? '')) . '</dd>'
            . '<dt>Created</dt><dd>' . AdminLayout::escape((string) $job['created_at']) . '</dd>'
            . '<dt>Updated</dt><dd>' . AdminLayout::escape((string) ($job['updated_at'] ?? '')) . '</dd>'
            . '</dl>'
            . '<h2>Payload</h2><pre>' . AdminLayout::escape((string) $payloadPretty) . '</pre>'
            . '<h2>Last Error</h2><pre>' . AdminLayout::escape((string) ($job['last_error'] ?? '')) . '</pre>'
            . '<h2>Attempt History</h2>'
            . '<table class="admin-table"><thead><tr><th>ID</th><th>Status</th><th>Message</th><th>Started</th><th>Finished</th><th>Created</th></tr></thead><tbody>' . $attemptRows . '</tbody></table>'
            . '<p><a class="admin-button" href="/platform/admin/jobs">Back to jobs</a></p>';

        return Response::html(AdminLayout::render(
            title: 'Background Job #' . $jobId,
            body: $body,
            nav: [
                '/admin' => 'Dashboard',
                '/admin/jobs' => 'Jobs',
                '/admin/audit-log' => 'Audit Log',
                '/admin/routes' => 'Routes',
            ],
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
            return Response::html('<h1>Job service unavailable</h1>', 500);
        }

        $jobId = (int) ($_POST['job_id'] ?? 0);
        $action = (string) ($_POST['job_admin_action'] ?? '');

        if ($jobId <= 0) {
            return Response::html('<h1>Invalid job id</h1>', 422);
        }

        if ($action === 'requeue') {
            $this->service->requeue($jobId);
            FlashMessages::success("Requeued job {$jobId}.");
            $this->auditAction($request, $currentUser, 'platform.background_job.requeued', (string) $jobId);
        } elseif ($action === 'cancel') {
            $this->service->cancel($jobId);
            FlashMessages::success("Cancelled queued job {$jobId}.");
            $this->auditAction($request, $currentUser, 'platform.background_job.cancelled', (string) $jobId);
        } else {
            return Response::html('<h1>Invalid job action</h1>', 422);
        }

        return new Response('', 302, ['Location' => '/platform/admin/jobs']);
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $status = trim((string) ($_GET['status'] ?? ''));
        $jobType = trim((string) ($_GET['job_type'] ?? ''));
        $page = Pagination::pageFromQuery($_GET['page'] ?? 1);
        $limit = Pagination::limitFromQuery($_GET['limit'] ?? 50);
        $offset = Pagination::offset($page, $limit);
        $csrf = AdminLayout::escape($this->csrf?->getOrCreate() ?? '');

        $rows = '';

        foreach ($this->jobs->latest(
            status: $status !== '' ? $status : null,
            jobType: $jobType !== '' ? $jobType : null,
            limit: $limit,
            offset: $offset,
        ) as $job) {
            $jobId = (int) $job['id'];
            $payloadPreview = mb_substr((string) ($job['payload'] ?? ''), 0, 180);
            $errorPreview = mb_substr((string) ($job['last_error'] ?? ''), 0, 180);

            $actions = <<<HTML
<form class="admin-inline-form" method="post" action="/platform/admin/jobs/action">
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <input type="hidden" name="job_id" value="{$jobId}">
    <input type="hidden" name="job_admin_action" value="requeue">
    <button type="submit">Requeue</button>
</form>
<form class="admin-inline-form" method="post" action="/platform/admin/jobs/action">
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <input type="hidden" name="job_id" value="{$jobId}">
    <input type="hidden" name="job_admin_action" value="cancel">
    <button type="submit">Cancel</button>
</form>
HTML;

            $rows .= '<tr>'
                . '<td><a href="/platform/admin/jobs/' . $jobId . '">' . AdminLayout::escape((string) $job['id']) . '</a></td>'
                . '<td>' . AdminLayout::escape((string) ($job['tenant_slug'] ?? $job['tenant_id'] ?? '')) . '</td>'
                . '<td>' . AdminLayout::escape((string) $job['job_type']) . '</td>'
                . '<td>' . AdminLayout::escape((string) $job['status']) . '</td>'
                . '<td>' . AdminLayout::escape((string) ($job['attempts'] ?? '')) . ' / ' . AdminLayout::escape((string) ($job['attempt_history_count'] ?? 0)) . '</td>'
                . '<td><code>' . AdminLayout::escape($payloadPreview) . '</code></td>'
                . '<td>' . AdminLayout::escape($errorPreview) . '</td>'
                . '<td>' . AdminLayout::escape((string) $job['created_at']) . '</td>'
                . '<td>' . AdminLayout::escape((string) ($job['updated_at'] ?? '')) . '</td>'
                . '<td>' . $actions . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="10">No background jobs found.</td></tr>';
        }

        $statusValue = AdminLayout::escape($status);
        $jobTypeValue = AdminLayout::escape($jobType);
        $query = ['status' => $status, 'job_type' => $jobType, 'limit' => $limit];

        $prevUrl = Pagination::previousPageUrl('/platform/admin/jobs', $query, $page);
        $nextUrl = Pagination::nextPageUrl('/platform/admin/jobs', $query, $page);
        $pager = '<p>'
            . ($prevUrl ? '<a class="admin-button" href="' . AdminLayout::escape($prevUrl) . '">Previous</a> ' : '')
            . '<span class="admin-muted">Page ' . $page . '</span> '
            . '<a class="admin-button" href="' . AdminLayout::escape($nextUrl) . '">Next</a>'
            . '</p>';

        return Response::html(AdminLayout::render(
            title: 'Background Jobs | Platform Admin',
            body: <<<HTML
<p class="admin-notice admin-notice-warning"><strong>Queued jobs require the background worker.</strong> Production deploy now checks <code>artsfolio-background-worker.service</code>. If every row stays queued, run <code>systemctl status artsfolio-background-worker.service</code> and <code>ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env php /var/www/artsfolio/scripts/workers/run_once.php</code> on the server.</p>
<form class="admin-form" method="get" action="/platform/admin/jobs">
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
    <a class="admin-button" href="/platform/admin/jobs">Clear</a>
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
                '/admin/jobs' => 'Jobs',
                '/admin/email-outbox' => 'Email Outbox',
                '/admin/audit-log' => 'Audit Log',
                '/admin/platform-settings' => 'Settings',
                '/admin/routes' => 'Routes',
            ],
        ));
    }

    private function auditAction(Request $request, ?array $currentUser, string $action, string $entityId): void
    {
        if (!$this->auditLog) {
            return;
        }

        $this->auditLog->record(
            action: $action,
            userId: isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null,
            entityType: 'background_job',
            entityId: $entityId,
            details: [],
            ipAddress: $request->server('REMOTE_ADDR'),
        );
    }
}

// End of file.
