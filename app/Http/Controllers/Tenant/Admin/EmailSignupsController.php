<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Membership\Roles;
use App\Platform\Tenancy\TenantContext;
use App\Support\Csv\CsvResponse;
use App\Support\Pagination\Pagination;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Signup\EmailSignupRepository;

/**
 * Handles tenant-admin email signup list, export, and consent actions.
 */
final class EmailSignupsController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly EmailSignupRepository $signups,
        private readonly ?CsrfTokenService $csrf = null,
        private readonly ?AuditLogRepository $auditLog = null,
    ) {
    }

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canView($currentUser, $tenant)) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $rows = '';
        $csrf = $this->escape($this->csrf?->getOrCreate() ?? '');
        $page = Pagination::pageFromQuery($_GET['page'] ?? 1);
        $limit = Pagination::limitFromQuery($_GET['limit'] ?? 50);
        $offset = Pagination::offset($page, $limit);

        foreach ($this->signups->latestForTenant($tenant, $limit, $offset) as $signup) {
            $id = (int) $signup['id'];

            $actions = <<<HTML
<form method="post" action="/admin/email-signups/consent" class="admin-inline-form">
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <input type="hidden" name="signup_id" value="{$id}">
    <input type="hidden" name="status" value="confirmed">
    <button type="submit">Confirm</button>
</form>
<form method="post" action="/admin/email-signups/consent" class="admin-inline-form">
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <input type="hidden" name="signup_id" value="{$id}">
    <input type="hidden" name="status" value="unsubscribed">
    <button type="submit">Unsubscribe</button>
</form>
HTML;

            $rows .= '<tr>'
                . '<td>' . $this->escape((string) $signup['id']) . '</td>'
                . '<td>' . $this->escape((string) $signup['email']) . '</td>'
                . '<td>' . $this->escape((string) ($signup['name'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) ($signup['source'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) $signup['consent_status']) . '</td>'
                . '<td>' . $this->escape((string) $signup['created_at']) . '</td>'
                . '<td>' . $actions . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="7">No email signups found.</td></tr>';
        }

        $tenantName = $this->escape($tenant->name);

        return Response::html(AdminLayout::render(
            title: 'Email Signups | ' . $tenantName,
            body: <<<HTML
<p><a class="admin-button" href="/admin/email-signups.csv">Export CSV</a></p>

<table class="admin-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Email</th>
            <th>Name</th>
            <th>Source</th>
            <th>Consent</th>
            <th>Created</th>
            <th>Actions</th>
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
            ],
        ));
    }

    public function updateConsent(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canView($currentUser, $tenant)) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        if (!$this->csrf || !$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }

        $signupId = (int) ($_POST['signup_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');

        if ($signupId <= 0) {
            return Response::html('<h1>Invalid signup id</h1>', 422);
        }

        try {
            $this->signups->updateConsentStatus($tenant, $signupId, $status);
            $this->auditAction(
                request: $request,
                tenant: $tenant,
                currentUser: $currentUser,
                action: 'tenant.email_signup.consent_changed',
                entityId: (string) $signupId,
                details: ['status' => $status],
            );
        } catch (\Throwable $e) {
            return Response::html('<h1>Invalid consent update</h1>', 422);
        }

        return new Response('', 302, ['Location' => '/admin/email-signups']);
    }

    public function export(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canView($currentUser, $tenant)) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $rows = [];

        foreach ($this->signups->latestForTenant($tenant, 1000) as $signup) {
            $rows[] = [
                'id' => (string) $signup['id'],
                'email' => (string) $signup['email'],
                'name' => (string) ($signup['name'] ?? ''),
                'source' => (string) ($signup['source'] ?? ''),
                'consent_status' => (string) $signup['consent_status'],
                'confirmed_at' => (string) ($signup['confirmed_at'] ?? ''),
                'unsubscribed_at' => (string) ($signup['unsubscribed_at'] ?? ''),
                'created_at' => (string) $signup['created_at'],
            ];
        }

        return CsvResponse::download(
            filename: 'email-signups-' . $tenant->slug . '.csv',
            headers: ['id', 'email', 'name', 'source', 'consent_status', 'confirmed_at', 'unsubscribed_at', 'created_at'],
            rows: $rows,
        );
    }

    private function auditAction(
        Request $request,
        TenantContext $tenant,
        ?array $currentUser,
        string $action,
        string $entityId,
        array $details = [],
    ): void {
        if (!$this->auditLog) {
            return;
        }

        $this->auditLog->record(
            action: $action,
            tenantId: $tenant->tenantId,
            userId: isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null,
            entityType: 'email_signup',
            entityId: $entityId,
            details: $details,
            ipAddress: $request->server('REMOTE_ADDR'),
        );
    }

    private function canView(?array $currentUser, TenantContext $tenant): bool
    {
        return $this->roles->allows(
            currentUser: $currentUser,
            tenant: $tenant,
            allowedRoles: [Roles::TENANT_OWNER, Roles::TENANT_ADMIN, Roles::TENANT_EDITOR],
        );
    }

    private function escape(string $value): string
    {
        return AdminLayout::escape($value);
    }
}

// End of file.
