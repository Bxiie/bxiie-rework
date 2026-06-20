<?php

/**
 * Tenant admin email signup list management.
 */

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Http\View\ErrorPage;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Membership\Roles;
use App\Platform\Tenancy\TenantContext;
use App\Support\Csv\CsvResponse;
use App\Support\Flash\FlashMessages;
use App\Support\Pagination\Pagination;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Signup\EmailSignupRepository;

/**
 * Handles tenant-admin email signup search, sort, import, export, edit, and delete actions.
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
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        $csrf = $this->escape($this->csrf?->getOrCreate() ?? '');
        $query = trim((string) ($_GET['q'] ?? ''));
        $sort = $this->safeSort((string) ($_GET['sort'] ?? 'created_at'));
        $direction = strtolower((string) ($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $page = Pagination::pageFromQuery($_GET['page'] ?? 1);
        $limit = Pagination::limitFromQuery($_GET['limit'] ?? 50);
        $offset = Pagination::offset($page, $limit);
        $total = $this->signups->countForTenant($tenant, $query);
        $rows = '';
        $returnTo = $this->escape($this->currentListPath());

        foreach ($this->signups->searchForTenant($tenant, $query, $sort, $direction, $limit, $offset) as $signup) {
            $id = (int) $signup['id'];
            $ip = $this->escape((string) ($signup['ip_address'] ?? ''));
            $location = $this->escape($this->formatLocation($signup));
            $notes = $this->escape((string) ($signup['notes'] ?? ''));
            $name = $this->escape((string) ($signup['name'] ?? ''));
            $source = $this->escape((string) ($signup['source'] ?? ''));
            $email = $this->escape((string) $signup['email']);
            $status = $this->escape((string) $signup['consent_status']);
            $created = $this->escape((string) $signup['created_at']);

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
<form method="post" action="/admin/email-signups/delete" class="admin-inline-form" onsubmit="return confirm('Delete this email address from the list?');">
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <input type="hidden" name="signup_id" value="{$id}">
    <input type="hidden" name="return_to" value="{$returnTo}">
    <button type="submit" class="danger">Delete</button>
</form>
HTML;

            $rows .= <<<HTML
<tr>
    <td>{$id}</td>
    <td>{$email}</td>
    <td>
        <form method="post" action="/admin/email-signups/update" class="stacked-form compact-form">
            <input type="hidden" name="csrf_token" value="{$csrf}">
            <input type="hidden" name="signup_id" value="{$id}">
            <input type="hidden" name="return_to" value="{$returnTo}">
            <label>Name <input name="name" value="{$name}"></label>
            <label>Source <input name="source" value="{$source}"></label>
            <label>Notes <textarea name="notes" rows="2">{$notes}</textarea></label>
            <button type="submit">Save</button>
        </form>
    </td>
    <td>{$source}<br><small>IP: {$ip}<br>Location: {$location}</small></td>
    <td>{$status}</td>
    <td>{$created}</td>
    <td>{$actions}</td>
</tr>
HTML;
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="7">No email signups found.</td></tr>';
        }

        $tenantName = $this->escape($tenant->name);
        $queryEscaped = $this->escape($query);
        $nextDir = $direction === 'asc' ? 'desc' : 'asc';
        $summary = $this->escape(number_format($total) . ' email address' . ($total === 1 ? '' : 'es'));

        return Response::html(AdminLayout::render(
            title: 'Email Signups | ' . $tenantName,
            body: <<<HTML
<section class="admin-panel">
    <h2>Email list</h2>
    <p class="admin-muted">Search, sort, import, annotate, export, unsubscribe, or delete tenant email-list addresses.</p>
    <form method="get" action="/admin/email-signups" class="admin-filter-form">
        <label>Search <input type="search" name="q" value="{$queryEscaped}" placeholder="email, name, source, notes, status"></label>
        <label>Sort
            <select name="sort">
                {$this->sortOption('created_at', 'Created', $sort)}
                {$this->sortOption('email', 'Email', $sort)}
                {$this->sortOption('name', 'Name', $sort)}
                {$this->sortOption('source', 'Source', $sort)}
                {$this->sortOption('consent_status', 'Consent', $sort)}
                {$this->sortOption('updated_at', 'Updated', $sort)}
            </select>
        </label>
        <label>Direction
            <select name="dir">
                <option value="desc"{$this->selected($direction, 'desc')}>Newest/Z-A</option>
                <option value="asc"{$this->selected($direction, 'asc')}>Oldest/A-Z</option>
            </select>
        </label>
        <button type="submit">Apply</button>
        <a class="admin-button" href="/admin/email-signups.csv?q={$this->url($query)}&sort={$this->url($sort)}&dir={$this->url($direction)}">Export CSV</a>
    </form>
</section>

<section class="admin-panel">
    <h2>Import CSV</h2>
    <p class="admin-muted">CSV headers: email, name, source, notes, consent_status. Email is required. Existing addresses are updated, not duplicated.</p>
    <form method="post" action="/admin/email-signups/import" enctype="multipart/form-data" class="admin-filter-form">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <label>Default source <input name="source" placeholder="studio fair, Mailchimp import, collector list"></label>
        <label>CSV file <input type="file" name="csv" accept=".csv,text/csv" required></label>
        <button type="submit">Import CSV</button>
    </form>
</section>

<p class="admin-muted">Showing {$summary}.</p>
<div class="admin-table-wrap"><table class="admin-table">
    <thead>
        <tr>
            <th>ID</th>
            <th><a href="/admin/email-signups?q={$this->url($query)}&sort=email&dir={$nextDir}">Email</a></th>
            <th>Name / Source / Notes</th>
            <th><a href="/admin/email-signups?q={$this->url($query)}&sort=source&dir={$nextDir}">Source / IP / Location</a></th>
            <th><a href="/admin/email-signups?q={$this->url($query)}&sort=consent_status&dir={$nextDir}">Consent</a></th>
            <th><a href="/admin/email-signups?q={$this->url($query)}&sort=created_at&dir={$nextDir}">Created</a></th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>{$rows}</tbody>
</table></div>
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
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }
        if (!$this->validCsrf()) {
            return Response::invalidCsrf();
        }

        $signupId = (int) ($_POST['signup_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        if ($signupId <= 0) {
            return Response::html('<h1>Invalid signup id</h1>', 422);
        }

        $this->signups->updateConsentStatus($tenant, $signupId, $status);
        FlashMessages::success('Email signup consent updated.');
        $this->auditAction($request, $tenant, $currentUser, 'tenant.email_signup.consent_changed', (string) $signupId, ['status' => $status]);

        return new Response('', 303, ['Location' => $this->returnTo('/admin/email-signups')]);
    }

    public function update(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser, $tenant)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }
        if (!$this->validCsrf()) {
            return Response::invalidCsrf();
        }

        $signupId = (int) ($_POST['signup_id'] ?? 0);
        if ($signupId <= 0) {
            return Response::html('<h1>Invalid signup id</h1>', 422);
        }

        $this->signups->updateAdminFields($tenant, $signupId, (string) ($_POST['name'] ?? ''), (string) ($_POST['source'] ?? ''), (string) ($_POST['notes'] ?? ''));
        FlashMessages::success('Email signup details updated.');
        $this->auditAction($request, $tenant, $currentUser, 'tenant.email_signup.updated', (string) $signupId);

        return new Response('', 303, ['Location' => $this->returnTo('/admin/email-signups')]);
    }

    public function delete(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser, $tenant)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }
        if (!$this->validCsrf()) {
            return Response::invalidCsrf();
        }

        $signupId = (int) ($_POST['signup_id'] ?? 0);
        if ($signupId <= 0) {
            return Response::html('<h1>Invalid signup id</h1>', 422);
        }

        $this->signups->delete($tenant, $signupId);
        FlashMessages::success('Email address deleted from list.');
        $this->auditAction($request, $tenant, $currentUser, 'tenant.email_signup.deleted', (string) $signupId);

        return new Response('', 303, ['Location' => $this->returnTo('/admin/email-signups')]);
    }

    public function import(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser, $tenant)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }
        if (!$this->validCsrf()) {
            return Response::invalidCsrf();
        }

        $file = $_FILES['csv'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return Response::html('<h1>CSV upload failed</h1>', 422);
        }

        $defaultSource = trim((string) ($_POST['source'] ?? 'CSV import'));
        $imported = 0;
        $handle = fopen((string) $file['tmp_name'], 'rb');
        if (!$handle) {
            return Response::html('<h1>Could not read CSV upload</h1>', 422);
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return Response::html('<h1>CSV file is empty</h1>', 422);
        }
        $map = $this->csvHeaderMap($headers);

        while (($row = fgetcsv($handle)) !== false) {
            $email = $this->csvValue($row, $map, 'email');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $this->signups->upsert(
                tenant: $tenant,
                email: $email,
                name: $this->csvValue($row, $map, 'name'),
                source: $this->csvValue($row, $map, 'source') ?: $defaultSource,
                consentStatus: $this->safeConsent($this->csvValue($row, $map, 'consent_status')),
                notes: $this->csvValue($row, $map, 'notes'),
            );
            $imported++;
        }
        fclose($handle);

        FlashMessages::success('Imported ' . number_format($imported) . ' email address' . ($imported === 1 ? '' : 'es') . '.');
        $this->auditAction($request, $tenant, $currentUser, 'tenant.email_signup.imported', 'csv', ['count' => $imported, 'source' => $defaultSource]);

        return new Response('', 303, ['Location' => '/admin/email-signups']);
    }

    public function export(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canView($currentUser, $tenant)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        $query = trim((string) ($_GET['q'] ?? ''));
        $sort = $this->safeSort((string) ($_GET['sort'] ?? 'created_at'));
        $direction = strtolower((string) ($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $rows = [];

        foreach ($this->signups->searchForTenant($tenant, $query, $sort, $direction, 5000) as $signup) {
            $rows[] = [
                'id' => (string) $signup['id'],
                'email' => (string) $signup['email'],
                'name' => (string) ($signup['name'] ?? ''),
                'source' => (string) ($signup['source'] ?? ''),
                'notes' => (string) ($signup['notes'] ?? ''),
                'consent_status' => (string) $signup['consent_status'],
                'ip_address' => (string) ($signup['ip_address'] ?? ''),
                'city' => (string) ($signup['city'] ?? ''),
                'region' => (string) ($signup['region'] ?? ''),
                'country' => (string) ($signup['country'] ?? ''),
                'confirmed_at' => (string) ($signup['confirmed_at'] ?? ''),
                'unsubscribed_at' => (string) ($signup['unsubscribed_at'] ?? ''),
                'created_at' => (string) $signup['created_at'],
            ];
        }

        return CsvResponse::download(
            filename: 'email-signups-' . $tenant->slug . '.csv',
            headers: ['id', 'email', 'name', 'source', 'notes', 'ip_address', 'city', 'region', 'country', 'consent_status', 'confirmed_at', 'unsubscribed_at', 'created_at'],
            rows: $rows,
        );
    }

    private function auditAction(Request $request, TenantContext $tenant, ?array $currentUser, string $action, string $entityId, array $details = []): void
    {
        $this->auditLog?->record(
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
        return $this->roles->allows($currentUser, $tenant, [Roles::TENANT_OWNER, Roles::TENANT_ADMIN, Roles::TENANT_EDITOR]);
    }

    private function canManage(?array $currentUser, TenantContext $tenant): bool
    {
        return $this->roles->allows($currentUser, $tenant, [Roles::TENANT_OWNER, Roles::TENANT_ADMIN]);
    }

    private function validCsrf(): bool
    {
        return $this->csrf !== null && $this->csrf->validate($_POST['csrf_token'] ?? null);
    }

    /** @param array<string,mixed> $row */
    private function formatLocation(array $row): string
    {
        $parts = array_filter([
            trim((string) ($row['city'] ?? '')),
            trim((string) ($row['region'] ?? '')),
            trim((string) ($row['country'] ?? '')),
        ], static fn (string $part): bool => $part !== '');

        return $parts ? implode(', ', $parts) : 'Unknown';
    }

    /** @param list<string> $headers @return array<string,int> */
    private function csvHeaderMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $index => $header) {
            $key = strtolower(trim((string) $header));
            $key = str_replace([' ', '-'], '_', $key);
            $map[$key] = (int) $index;
        }

        return $map;
    }

    /** @param list<string|null> $row @param array<string,int> $map */
    private function csvValue(array $row, array $map, string $key): string
    {
        if (!isset($map[$key])) {
            return '';
        }

        return trim((string) ($row[$map[$key]] ?? ''));
    }

    private function safeConsent(string $status): string
    {
        return in_array($status, ['pending', 'confirmed', 'unsubscribed'], true) ? $status : 'confirmed';
    }

    private function safeSort(string $sort): string
    {
        return in_array($sort, ['email', 'name', 'source', 'consent_status', 'created_at', 'updated_at'], true) ? $sort : 'created_at';
    }

    private function currentListPath(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/admin/email-signups');
        return str_starts_with($uri, '/admin/email-signups') ? $uri : '/admin/email-signups';
    }

    private function returnTo(string $fallback): string
    {
        $returnTo = (string) ($_POST['return_to'] ?? '');
        return str_starts_with($returnTo, '/admin/email-signups') ? $returnTo : $fallback;
    }

    private function sortOption(string $value, string $label, string $current): string
    {
        return '<option value="' . $this->escape($value) . '"' . $this->selected($current, $value) . '>' . $this->escape($label) . '</option>';
    }

    private function selected(string $current, string $value): string
    {
        return $current === $value ? ' selected' : '';
    }

    private function url(string $value): string
    {
        return rawurlencode($value);
    }

    private function escape(string $value): string
    {
        return AdminLayout::escape($value);
    }
}

// End of file.
