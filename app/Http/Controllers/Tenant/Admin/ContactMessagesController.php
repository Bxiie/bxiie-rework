<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Membership\Roles;
use App\Platform\Tenancy\TenantContext;
use App\Support\Csv\CsvResponse;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Contact\ContactMessageRepository;

/**
 * Handles tenant-admin contact message list, export, and status actions.
 */
final class ContactMessagesController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly ContactMessageRepository $messages,
        private readonly ?CsrfTokenService $csrf = null,
    ) {
    }

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canView($currentUser, $tenant)) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $rows = '';
        $csrf = $this->escape($this->csrf?->getOrCreate() ?? '');

        foreach ($this->messages->latestForTenant($tenant, 50) as $message) {
            $preview = mb_substr((string) $message['message'], 0, 160);
            $id = (int) $message['id'];

            $actions = <<<HTML
<form method="post" action="/admin/contact-messages/status" style="display:inline">
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <input type="hidden" name="message_id" value="{$id}">
    <input type="hidden" name="status" value="read">
    <button type="submit">Read</button>
</form>
<form method="post" action="/admin/contact-messages/status" style="display:inline">
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <input type="hidden" name="message_id" value="{$id}">
    <input type="hidden" name="status" value="archived">
    <button type="submit">Archive</button>
</form>
<form method="post" action="/admin/contact-messages/status" style="display:inline">
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <input type="hidden" name="message_id" value="{$id}">
    <input type="hidden" name="status" value="spam">
    <button type="submit">Spam</button>
</form>
HTML;

            $rows .= '<tr>'
                . '<td>' . $this->escape((string) $message['id']) . '</td>'
                . '<td>' . $this->escape((string) $message['status']) . '</td>'
                . '<td>' . $this->escape((string) $message['sender_name']) . '</td>'
                . '<td>' . $this->escape((string) $message['sender_email']) . '</td>'
                . '<td>' . $this->escape((string) ($message['subject'] ?? '')) . '</td>'
                . '<td>' . $this->escape($preview) . '</td>'
                . '<td>' . $this->escape((string) $message['created_at']) . '</td>'
                . '<td>' . $actions . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="8">No contact messages found.</td></tr>';
        }

        $tenantName = $this->escape($tenant->name);

        return Response::html(<<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Contact Messages | {$tenantName}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<h1>Contact Messages</h1>
<p><a href="/admin/contact-messages.csv">Export CSV</a></p>

<table border="1" cellpadding="6" cellspacing="0">
    <thead>
        <tr>
            <th>ID</th>
            <th>Status</th>
            <th>Name</th>
            <th>Email</th>
            <th>Subject</th>
            <th>Message Preview</th>
            <th>Created</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        {$rows}
    </tbody>
</table>

<p><a href="/admin">Back to tenant admin</a></p>
</body>
</html>
HTML);
    }

    public function updateStatus(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser, $tenant)) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        if (!$this->csrf || !$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }

        $messageId = (int) ($_POST['message_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');

        if ($messageId <= 0) {
            return Response::html('<h1>Invalid message id</h1>', 422);
        }

        try {
            $this->messages->updateStatus($tenant, $messageId, $status);
        } catch (\Throwable $e) {
            return Response::html('<h1>Invalid status update</h1>', 422);
        }

        return new Response('', 302, ['Location' => '/admin/contact-messages']);
    }

    public function export(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canView($currentUser, $tenant)) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $rows = [];

        foreach ($this->messages->latestForTenant($tenant, 1000) as $message) {
            $rows[] = [
                'id' => (string) $message['id'],
                'status' => (string) $message['status'],
                'sender_name' => (string) $message['sender_name'],
                'sender_email' => (string) $message['sender_email'],
                'subject' => (string) ($message['subject'] ?? ''),
                'message' => (string) $message['message'],
                'ip_address' => (string) ($message['ip_address'] ?? ''),
                'created_at' => (string) $message['created_at'],
            ];
        }

        return CsvResponse::download(
            filename: 'contact-messages-' . $tenant->slug . '.csv',
            headers: ['id', 'status', 'sender_name', 'sender_email', 'subject', 'message', 'ip_address', 'created_at'],
            rows: $rows,
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

    private function canManage(?array $currentUser, TenantContext $tenant): bool
    {
        return $this->canView($currentUser, $tenant);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
