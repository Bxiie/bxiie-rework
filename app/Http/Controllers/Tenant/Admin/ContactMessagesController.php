<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Contact\ContactMessageRepository;
use PDO;
use ReflectionClass;

/**
 * Tenant admin contact-message management.
 *
 * Provides a table-oriented view with search, status filtering, archive/restore,
 * hard delete, and CSV export. The code intentionally keeps the original
 * constructor shape so existing route wiring remains compatible.
 */
final class ContactMessagesController
{
    private PDO $pdo;

    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly ContactMessageRepository $messages,
        private readonly CsrfTokenService $csrf,
        private readonly AuditLogRepository $auditLog,
    ) {
        $this->pdo = $this->extractPdo($messages);
    }

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser, $tenant)) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $q = trim((string) ($_GET['q'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $sort = trim((string) ($_GET['sort'] ?? 'newest'));
        $notice = trim((string) ($_GET['notice'] ?? ''));

        [$whereSql, $params] = $this->messageFilters($tenant, $q, $status);
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
            $query = http_build_query(array_filter(['q' => $q, 'status' => $status, 'sort' => $sort], static fn ($v) => $v !== ''));
            $returnTo = '/admin/contact-messages' . ($query ? '?' . $query : '');
            $returnToEsc = $this->escape($returnTo);

            $bodyRows .= <<<HTML
<tr>
    <td>{$created}</td>
    <td><strong>{$name}</strong><br><a href="mailto:{$email}">{$email}</a></td>
    <td>{$subject}</td>
    <td><div class="admin-message-preview">{$message}</div></td>
    <td>{$rowStatus}</td>
    <td class="admin-actions">
        <form method="post" action="/admin/contact-messages/status">
            <input type="hidden" name="csrf_token" value="{$csrf}">
            <input type="hidden" name="id" value="{$id}">
            <input type="hidden" name="return_to" value="{$returnToEsc}">
            <select name="status">
                <option value="new">New</option>
                <option value="read">Read</option>
                <option value="archived">Archived</option>
            </select>
            <button type="submit">Set</button>
        </form>
        <form method="post" action="/admin/contact-messages/delete" onsubmit="return confirm('Archive this contact message?');">
            <input type="hidden" name="csrf_token" value="{$csrf}">
            <input type="hidden" name="id" value="{$id}">
            <input type="hidden" name="mode" value="soft">
            <input type="hidden" name="return_to" value="{$returnToEsc}">
            <button type="submit">Archive</button>
        </form>
        <form method="post" action="/admin/contact-messages/delete" onsubmit="return confirm('Permanently delete this contact message? This cannot be undone.');">
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
            $bodyRows = '<tr><td colspan="6">No contact messages match this filter.</td></tr>';
        }

        $body = <<<HTML
<p><a href="/admin">&larr; Tenant Admin</a></p>
<h1>Contact Messages</h1>
{$noticeHtml}

<form method="get" action="/admin/contact-messages" class="admin-filter-bar">
    <label>Search<br><input type="search" name="q" value="{$qValue}" placeholder="Name, email, subject, message"></label>
    <label>Status<br>
        <select name="status">
            <option value=""{$statusOption('')}>All</option>
            <option value="new"{$statusOption('new')}>New</option>
            <option value="read"{$statusOption('read')}>Read</option>
            <option value="archived"{$statusOption('archived')}>Archived</option>
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
    <a href="/admin/contact-messages">Clear</a>
    <a href="/admin/contact-messages.csv">Export CSV</a>
</form>

<div class="admin-table-wrap">
<table class="admin-table">
    <thead><tr><th>Date</th><th>Sender</th><th>Subject</th><th>Message</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>{$bodyRows}</tbody>
</table>
</div>
HTML;

        return Response::html(AdminLayout::render('Contact Messages', $body));
    }

    public function export(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser, $tenant)) {
            return Response::html('<h1>Forbidden</h1>', 403);
        }

        $stmt = $this->pdo->prepare('SELECT * FROM contact_messages WHERE tenant_id = :tenant_id ORDER BY created_at DESC, id DESC');
        $stmt->execute(['tenant_id' => $tenant->tenantId]);

        $out = fopen('php://temp', 'w+');
        fputcsv($out, ['created_at', 'status', 'name', 'email', 'subject', 'message']);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            fputcsv($out, [
                $row['created_at'] ?? '',
                $row['status'] ?? '',
                $row['sender_name'] ?? $row['name'] ?? '',
                $row['sender_email'] ?? $row['email'] ?? '',
                $row['subject'] ?? '',
                $row['message'] ?? '',
            ]);
        }
        rewind($out);
        $csv = stream_get_contents($out) ?: '';
        fclose($out);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="contact-messages.csv"',
        ]);
    }

    public function updateStatus(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser, $tenant)) {
            return Response::html('<h1>Forbidden</h1>', 403);
        }

        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::html('<h1>Invalid request</h1>', 419);
        }

        $id = (int) ($_POST['id'] ?? 0);
        $status = in_array(($_POST['status'] ?? 'new'), ['new', 'read', 'archived'], true) ? (string) $_POST['status'] : 'new';

        $stmt = $this->pdo->prepare('UPDATE contact_messages SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE tenant_id = :tenant_id AND id = :id');
        $stmt->execute(['status' => $status, 'tenant_id' => $tenant->tenantId, 'id' => $id]);

        $this->audit($request, $tenant, $currentUser, 'tenant.contact_message.status_changed', (string) $id, ['status' => $status]);

        return new Response('', 303, ['Location' => $this->safeReturnTo((string) ($_POST['return_to'] ?? '/admin/contact-messages')) . $this->noticeSuffix('status-updated')]);
    }

    public function delete(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser, $tenant)) {
            return Response::html('<h1>Forbidden</h1>', 403);
        }

        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::html('<h1>Invalid request</h1>', 419);
        }

        $id = (int) ($_POST['id'] ?? 0);
        $mode = (string) ($_POST['mode'] ?? 'soft');

        if ($mode === 'hard') {
            $stmt = $this->pdo->prepare('DELETE FROM contact_messages WHERE tenant_id = :tenant_id AND id = :id');
            $stmt->execute(['tenant_id' => $tenant->tenantId, 'id' => $id]);
            $this->audit($request, $tenant, $currentUser, 'tenant.contact_message.deleted', (string) $id, ['mode' => 'hard']);
            $notice = 'deleted';
        } else {
            $stmt = $this->pdo->prepare("UPDATE contact_messages SET status = 'archived', updated_at = CURRENT_TIMESTAMP WHERE tenant_id = :tenant_id AND id = :id");
            $stmt->execute(['tenant_id' => $tenant->tenantId, 'id' => $id]);
            $this->audit($request, $tenant, $currentUser, 'tenant.contact_message.archived', (string) $id, ['mode' => 'soft']);
            $notice = 'archived';
        }

        return new Response('', 303, ['Location' => $this->safeReturnTo((string) ($_POST['return_to'] ?? '/admin/contact-messages')) . $this->noticeSuffix($notice)]);
    }

    private function messageFilters(TenantContext $tenant, string $q, string $status): array
    {
        $where = ['tenant_id = :tenant_id'];
        $params = ['tenant_id' => $tenant->tenantId];

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

    private function extractPdo(ContactMessageRepository $repository): PDO
    {
        $reflection = new ReflectionClass($repository);
        foreach (['pdo', 'db'] as $propertyName) {
            if ($reflection->hasProperty($propertyName)) {
                $property = $reflection->getProperty($propertyName);
                $property->setAccessible(true);
                $value = $property->getValue($repository);
                if ($value instanceof PDO) {
                    return $value;
                }
            }
        }

        throw new \RuntimeException('Unable to extract PDO from ContactMessageRepository.');
    }

    private function canManage(?array $currentUser, TenantContext $tenant): bool
    {
        return $this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin']);
    }

    private function audit(Request $request, TenantContext $tenant, ?array $currentUser, string $action, string $entityId, array $details = []): void
    {
        $this->auditLog->record(
            $action,
            $tenant->tenantId,
            isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null,
            'contact_message',
            $entityId,
            $details,
            $request->server('REMOTE_ADDR'),
        );
    }

    private function safeReturnTo(string $returnTo): string
    {
        return str_starts_with($returnTo, '/admin/contact-messages') ? $returnTo : '/admin/contact-messages';
    }

    private function noticeSuffix(string $notice): string
    {
        return str_contains($notice, '?') ? '&notice=' . rawurlencode($notice) : (str_contains($_SERVER['REQUEST_URI'] ?? '', '?') ? '&notice=' . rawurlencode($notice) : (str_contains($notice, '?') ? '' : (str_contains('/admin/contact-messages', '?') ? '&' : '?') . 'notice=' . rawurlencode($notice)));
    }

    private function noticeText(string $notice): string
    {
        return match ($notice) {
            'status-updated' => 'Contact message status updated.',
            'archived' => 'Contact message archived.',
            'deleted' => 'Contact message permanently deleted.',
            default => 'Action complete.',
        };
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
