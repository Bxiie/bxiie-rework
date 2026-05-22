<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use PDO;

/**
 * Tenant engagement administration: contact messages and email subscribers.
 */
final class EngagementController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly PDO $pdo,
        private readonly CsrfTokenService $csrf,
    ) {
    }

    public function contacts(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->allowed($currentUser, $tenant)) {
            return Response::html('<h1>Forbidden</h1>', 403);
        }

        $notice = ($_GET['notice'] ?? '') === 'deleted'
            ? '<p class="admin-notice success">Contact message deleted.</p>'
            : '';

        $stmt = $this->pdo->prepare(
            'SELECT id, sender_name, sender_email, name, email, subject, message, status, created_at
             FROM contact_messages
             WHERE tenant_id = :tenant_id
             ORDER BY id DESC
             LIMIT 300'
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId]);

        $token = htmlspecialchars($this->csrf->getOrCreate(), ENT_QUOTES, 'UTF-8');
        $rows = '';

        foreach ($stmt->fetchAll() as $message) {
            $id = (int) $message['id'];
            $name = $this->e((string) (($message['sender_name'] ?? '') ?: ($message['name'] ?? '')));
            $email = $this->e((string) (($message['sender_email'] ?? '') ?: ($message['email'] ?? '')));
            $subject = $this->e((string) ($message['subject'] ?? ''));
            $created = $this->e((string) $message['created_at']);
            $body = nl2br($this->e((string) ($message['message'] ?? '')));
            $rows .= <<<HTML
<article class="admin-card">
    <h2>{$subject}</h2>
    <p><strong>{$name}</strong> &lt;{$email}&gt; · {$created}</p>
    <div>{$body}</div>
    <form method="post" action="/admin/contact-messages/delete" onsubmit="return confirm('Delete this contact message?');">
        <input type="hidden" name="csrf_token" value="{$token}">
        <input type="hidden" name="id" value="{$id}">
        <button type="submit">Delete</button>
    </form>
</article>
HTML;
        }

        if ($rows === '') {
            $rows = '<p>No contact messages.</p>';
        }

        return Response::html($this->adminPage('Contact messages', $notice . $rows));
    }

    public function deleteContact(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->allowed($currentUser, $tenant)) {
            return Response::html('<h1>Forbidden</h1>', 403);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::html('<h1>Invalid request</h1>', 419);
        }

        $stmt = $this->pdo->prepare('DELETE FROM contact_messages WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute([
            'id' => (int) ($_POST['id'] ?? 0),
            'tenant_id' => $tenant->tenantId,
        ]);

        return new Response('', 303, ['Location' => '/admin/contact-messages?notice=deleted']);
    }

    public function subscribers(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->allowed($currentUser, $tenant)) {
            return Response::html('<h1>Forbidden</h1>', 403);
        }

        $notice = match ((string) ($_GET['notice'] ?? '')) {
            'imported' => '<p class="admin-notice success">Subscribers imported.</p>',
            default => '',
        };

        $stmt = $this->pdo->prepare(
            'SELECT id, email, name, source, consent_status, created_at
             FROM email_signups
             WHERE tenant_id = :tenant_id
             ORDER BY id DESC
             LIMIT 500'
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId]);

        $token = htmlspecialchars($this->csrf->getOrCreate(), ENT_QUOTES, 'UTF-8');
        $rows = '';
        foreach ($stmt->fetchAll() as $sub) {
            $rows .= '<tr>'
                . '<td>' . $this->e((string) ($sub['email'] ?? '')) . '</td>'
                . '<td>' . $this->e((string) ($sub['name'] ?? '')) . '</td>'
                . '<td>' . $this->e((string) ($sub['source'] ?? '')) . '</td>'
                . '<td>' . $this->e((string) ($sub['consent_status'] ?? '')) . '</td>'
                . '<td>' . $this->e((string) ($sub['created_at'] ?? '')) . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="5">No subscribers yet.</td></tr>';
        }

        $body = <<<HTML
{$notice}
<p><a href="/admin/email-signups.csv">Export CSV</a></p>
<form method="post" action="/admin/email-signups/import" enctype="multipart/form-data" class="admin-card">
    <input type="hidden" name="csrf_token" value="{$token}">
    <h2>Import subscribers</h2>
    <p>CSV columns: email, name. Extra columns are ignored.</p>
    <input type="file" name="csv" accept=".csv,text/csv" required>
    <button type="submit">Import CSV</button>
</form>
<table class="admin-table">
<thead><tr><th>Email</th><th>Name</th><th>Source</th><th>Status</th><th>Created</th></tr></thead>
<tbody>{$rows}</tbody>
</table>
HTML;

        return Response::html($this->adminPage('Email signups', $body));
    }

    public function importSubscribers(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->allowed($currentUser, $tenant)) {
            return Response::html('<h1>Forbidden</h1>', 403);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::html('<h1>Invalid request</h1>', 419);
        }

        $tmp = $_FILES['csv']['tmp_name'] ?? '';
        if (!is_uploaded_file((string) $tmp)) {
            return Response::html('<h1>Upload failed</h1><p>No CSV uploaded.</p>', 422);
        }

        $handle = fopen((string) $tmp, 'rb');
        if (!$handle) {
            return Response::html('<h1>Upload failed</h1><p>Could not read CSV.</p>', 422);
        }

        $headers = fgetcsv($handle) ?: [];
        $normalized = array_map(static fn ($h): string => strtolower(trim((string) $h)), $headers);
        $emailIndex = array_search('email', $normalized, true);
        $nameIndex = array_search('name', $normalized, true);
        if ($emailIndex === false) {
            fclose($handle);
            return Response::html('<h1>Invalid CSV</h1><p>Missing email column.</p>', 422);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO email_signups (tenant_id, email, name, source, consent_status, created_at, updated_at)
             VALUES (:tenant_id, :email, :name, :source, :consent_status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE name = VALUES(name), updated_at = CURRENT_TIMESTAMP'
        );

        while (($row = fgetcsv($handle)) !== false) {
            $email = strtolower(trim((string) ($row[$emailIndex] ?? '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $stmt->execute([
                'tenant_id' => $tenant->tenantId,
                'email' => $email,
                'name' => $nameIndex !== false ? trim((string) ($row[$nameIndex] ?? '')) : null,
                'source' => 'admin_import',
                'consent_status' => 'imported',
            ]);
        }
        fclose($handle);

        return new Response('', 303, ['Location' => '/admin/email-signups?notice=imported']);
    }

    private function allowed(?array $currentUser, TenantContext $tenant): bool
    {
        return $this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin']);
    }

    private function adminPage(string $title, string $body): string
    {
        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$this->e($title)} | Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/admin/admin.css">
</head>
<body>
<header class="admin-header"><a href="/admin">Admin</a><nav><a href="/admin/artworks">Artworks</a><a href="/admin/content">Content</a><a href="/admin/events">Events</a><a href="/admin/contact-messages">Messages</a><a href="/admin/email-signups">Email</a><a href="/admin/settings">Site</a></nav></header>
<main class="admin-main"><h1>{$this->e($title)}</h1>{$body}</main>
</body>
</html>
HTML;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
