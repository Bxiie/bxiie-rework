<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Http\View\ErrorPage;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Contact\PlatformContactMessageRepository;
use App\Platform\Membership\Roles;
use App\Support\Security\CsrfTokenService;
use PDO;

/**
 * Platform-admin workflow for public ArtsFolio contact submissions.
 *
 * These are the contacts submitted on artsfol.io/contact. Tenant contact
 * messages remain scoped to tenant admins at /admin/contact-messages.
 */
final class ContactMessagesController
{
    public function __construct(
        private readonly RequirePlatformRole $roles,
        private readonly PDO $pdo,
        private readonly CsrfTokenService $csrf,
        private readonly AuditLogRepository $auditLog,
        private readonly PlatformContactMessageRepository $messages,
    ) {
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $q = trim((string) ($_GET['q'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $sort = trim((string) ($_GET['sort'] ?? 'newest'));
        $notice = trim((string) ($_GET['notice'] ?? ''));

        [$whereSql, $params] = $this->messageFilters($q, $status);
        $orderSql = match ($sort) {
            'oldest' => 'created_at ASC, id ASC',
            'name' => 'COALESCE(sender_name, name, \'\') ASC, created_at DESC',
            'email' => 'COALESCE(sender_email, email, \'\') ASC, created_at DESC',
            'status' => 'status ASC, created_at DESC',
            default => 'created_at DESC, id DESC',
        };

        $stmt = $this->pdo->prepare("SELECT * FROM contact_messages {$whereSql} ORDER BY {$orderSql} LIMIT 250");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $csrf = $this->escape($this->csrf->getOrCreate());
        $qValue = $this->escape($q);
        $statusOption = fn (string $value): string => $status === $value ? ' selected' : '';
        $sortOption = fn (string $value): string => $sort === $value ? ' selected' : '';
        $noticeHtml = $notice !== '' ? '<p class="admin-notice">' . $this->escape($this->noticeText($notice)) . '</p>' : '';

        $bodyRows = '';
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $name = $this->escape((string) ($row['sender_name'] ?? $row['name'] ?? ''));
            $email = $this->escape((string) ($row['sender_email'] ?? $row['email'] ?? ''));
            $subject = $this->escape((string) ($row['subject'] ?? ''));
            $message = nl2br($this->escape((string) ($row['message'] ?? '')));
            $rowStatus = $this->escape((string) ($row['status'] ?? 'new'));
            $created = $this->escape((string) ($row['created_at'] ?? ''));
            $ip = $this->escape((string) ($row['ip_address'] ?? ''));
            $location = $this->escape($this->formatLocation($row));
            $query = http_build_query(array_filter(['q' => $q, 'status' => $status, 'sort' => $sort], static fn ($v) => $v !== ''));
            $returnTo = '/platform/admin/contacts' . ($query ? '?' . $query : '');
            $returnToEsc = $this->escape($returnTo);

            $bodyRows .= <<<HTML
<tr>
    <td>{$created}</td>
    <td><strong>{$name}</strong><br><a href="mailto:{$email}">{$email}</a><br><small>IP: {$ip}<br>Location: {$location}</small></td>
    <td>{$subject}</td>
    <td><div class="admin-message-preview">{$message}</div></td>
    <td>{$rowStatus}</td>
    <td class="admin-actions">
        <form method="post" action="/platform/admin/contacts/status">
            <input type="hidden" name="csrf_token" value="{$csrf}">
            <input type="hidden" name="id" value="{$id}">
            <input type="hidden" name="return_to" value="{$returnToEsc}">
            <select name="status">
                <option value="new">New</option>
                <option value="read">Read</option>
                <option value="archived">Archived</option>
                <option value="spam">Spam</option>
            </select>
            <button type="submit">Set</button>
        </form>
        <form method="post" action="/platform/admin/contacts/delete" onsubmit="return confirm('Archive this platform contact message?');">
            <input type="hidden" name="csrf_token" value="{$csrf}">
            <input type="hidden" name="id" value="{$id}">
            <input type="hidden" name="mode" value="soft">
            <input type="hidden" name="return_to" value="{$returnToEsc}">
            <button type="submit">Archive</button>
        </form>
        <form method="post" action="/platform/admin/contacts/delete" onsubmit="return confirm('Permanently delete this platform contact message? This cannot be undone.');">
            <input type="hidden" name="csrf_token" value="{$csrf}">
            <input type="hidden" name="id" value="{$id}">
            <input type="hidden" name="mode" value="hard">
            <input type="hidden" name="return_to" value="{$returnToEsc}">
            <button type="submit">Delete</button>
        </form>
    </td>
</tr>
HTML;
        }

        if ($bodyRows === '') {
            $bodyRows = '<tr><td colspan="6">No platform contact messages match this filter.</td></tr>';
        }

        $body = <<<HTML
{$noticeHtml}
<p class="admin-muted">Messages submitted on the public ArtsFolio platform contact form. Tenant contact messages are managed from each tenant admin.</p>

<form method="get" action="/platform/admin/contacts" class="admin-filter-bar">
    <label>Search<br><input type="search" name="q" value="{$qValue}" placeholder="Name, email, subject, message"></label>
    <label>Status<br>
        <select name="status">
            <option value=""{$statusOption('')}>All</option>
            <option value="new"{$statusOption('new')}>New</option>
            <option value="read"{$statusOption('read')}>Read</option>
            <option value="archived"{$statusOption('archived')}>Archived</option>
            <option value="spam"{$statusOption('spam')}>Spam</option>
        </select>
    </label>
    <label>Sort<br>
        <select name="sort">
            <option value="newest"{$sortOption('newest')}>Newest</option>
            <option value="oldest"{$sortOption('oldest')}>Oldest</option>
            <option value="name"{$sortOption('name')}>Name</option>
            <option value="email"{$sortOption('email')}>Email</option>
            <option value="status"{$sortOption('status')}>Status</option>
        </select>
    </label>
    <button type="submit">Apply</button>
    <a href="/platform/admin/contacts">Clear</a>
    <a href="/platform/admin/contacts.csv">Export CSV</a>
</form>

<div class="admin-table-wrap">
<table class="admin-table">
    <thead><tr><th>Date</th><th>Sender</th><th>Subject</th><th>Message</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>{$bodyRows}</tbody>
</table>
</div>
HTML;

        return Response::html(AdminLayout::render(title: 'Platform Contacts', body: $body, active: 'contacts'));
    }

    public function export(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login'), 403);
        }

        $stmt = $this->pdo->query('SELECT * FROM contact_messages WHERE tenant_id IS NULL ORDER BY created_at DESC, id DESC');

        $out = fopen('php://temp', 'w+');
        fputcsv($out, ['created_at', 'status', 'name', 'email', 'ip_address', 'city', 'region', 'country', 'subject', 'message']);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            fputcsv($out, [
                $row['created_at'] ?? '',
                $row['status'] ?? '',
                $row['sender_name'] ?? $row['name'] ?? '',
                $row['sender_email'] ?? $row['email'] ?? '',
                $row['ip_address'] ?? '',
                $row['city'] ?? '',
                $row['region'] ?? '',
                $row['country'] ?? '',
                $row['subject'] ?? '',
                $row['message'] ?? '',
            ]);
        }
        rewind($out);
        $csv = stream_get_contents($out) ?: '';
        fclose($out);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="platform-contact-messages.csv"',
        ]);
    }

    public function updateStatus(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login'), 403);
        }

        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::html('<h1>Invalid request</h1>', 419);
        }

        $id = (int) ($_POST['id'] ?? 0);
        $status = in_array(($_POST['status'] ?? 'new'), ['new', 'read', 'archived', 'spam'], true) ? (string) $_POST['status'] : 'new';
        $this->messages->updateStatus($id, $status);
        $this->audit($request, $currentUser, 'platform.contact_message.status_changed', (string) $id, ['status' => $status]);

        return new Response('', 303, ['Location' => $this->safeReturnTo((string) ($_POST['return_to'] ?? '/platform/admin/contacts')) . $this->noticeSuffix('status-updated')]);
    }

    public function delete(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login'), 403);
        }

        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::html('<h1>Invalid request</h1>', 419);
        }

        $id = (int) ($_POST['id'] ?? 0);
        $mode = (string) ($_POST['mode'] ?? 'soft');
        if ($mode === 'hard') {
            $this->messages->delete($id);
            $this->audit($request, $currentUser, 'platform.contact_message.deleted', (string) $id, ['mode' => 'hard']);
            $notice = 'deleted';
        } else {
            $this->messages->archive($id);
            $this->audit($request, $currentUser, 'platform.contact_message.archived', (string) $id, ['mode' => 'soft']);
            $notice = 'archived';
        }

        return new Response('', 303, ['Location' => $this->safeReturnTo((string) ($_POST['return_to'] ?? '/platform/admin/contacts')) . $this->noticeSuffix($notice)]);
    }

    private function messageFilters(string $q, string $status): array
    {
        $where = ['tenant_id IS NULL'];
        $params = [];

        if ($q !== '') {
            $where[] = '(COALESCE(sender_name, name, \'\') LIKE :q OR COALESCE(sender_email, email, \'\') LIKE :q OR COALESCE(subject, \'\') LIKE :q OR COALESCE(message, \'\') LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        if ($status !== '') {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function canManage(?array $currentUser): bool
    {
        return $this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT]);
    }

    private function audit(Request $request, ?array $currentUser, string $action, string $entityId, array $details = []): void
    {
        $this->auditLog->record(
            $action,
            null,
            isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null,
            'platform_contact_message',
            $entityId,
            $details,
            $request->server('REMOTE_ADDR'),
        );
    }

    private function safeReturnTo(string $returnTo): string
    {
        return str_starts_with($returnTo, '/platform/admin/contacts') ? $returnTo : '/platform/admin/contacts';
    }

    private function noticeSuffix(string $notice): string
    {
        return str_contains($_SERVER['REQUEST_URI'] ?? '', '?') ? '&notice=' . rawurlencode($notice) : '?notice=' . rawurlencode($notice);
    }

    private function noticeText(string $notice): string
    {
        return match ($notice) {
            'status-updated' => 'Platform contact message status updated.',
            'archived' => 'Platform contact message archived.',
            'deleted' => 'Platform contact message permanently deleted.',
            default => 'Action complete.',
        };
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

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
