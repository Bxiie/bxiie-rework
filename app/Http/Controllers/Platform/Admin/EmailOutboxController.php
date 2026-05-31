<?php

/**
 * Platform-admin email outbox diagnostics.
 */

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Http\View\ErrorPage;
use App\Platform\Email\EmailOutboxRepository;
use App\Platform\Membership\Roles;

/**
 * Handles the platform-admin email outbox list screen.
 */
final class EmailOutboxController
{
    public function __construct(
        private readonly RequirePlatformRole $roles,
        private readonly EmailOutboxRepository $outbox,
    ) {
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $rows = '';

        foreach ($this->outbox->latest(50) as $email) {
            $status = (string) $email['status'];
            $lastError = trim((string) ($email['last_error'] ?? ''));
            $diagnostic = $lastError !== ''
                ? '<details class="admin-error-details" open><summary>Last error</summary><pre>' . $this->escape($lastError) . '</pre></details>'
                : '<span class="admin-muted">None recorded</span>';

            $statusClass = $status === 'failed' ? ' status-failed' : '';

            $rows .= '<tr class="email-outbox-row' . $statusClass . '">'
                . '<td>' . $this->escape((string) $email['id']) . '</td>'
                . '<td>' . $this->escape($status) . '</td>'
                . '<td>' . $this->escape((string) $email['recipient_email']) . '</td>'
                . '<td>' . $this->escape((string) $email['subject']) . '</td>'
                . '<td>' . $this->escape((string) ($email['template_key'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) $email['attempts']) . '</td>'
                . '<td>' . $this->escape((string) $email['created_at']) . '</td>'
                . '<td>' . $diagnostic . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="8">No email outbox rows found.</td></tr>';
        }

        return Response::html(AdminLayout::render(
            title: 'Email Outbox | Platform Admin',
            body: <<<HTML
<p class="admin-muted">Failed rows show the stored SMTP or transport error from <code>email_outbox.last_error</code>.</p>
<table class="admin-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Status</th>
            <th>Recipient</th>
            <th>Subject</th>
            <th>Template</th>
            <th>Attempts</th>
            <th>Created</th>
            <th>Diagnostic</th>
        </tr>
    </thead>
    <tbody>
        {$rows}
    </tbody>
</table>
HTML,
            nav: [
                '/admin' => 'Dashboard',
                '/admin/tenants' => 'Tenants',
                '/admin/email-outbox' => 'Email Outbox',
                '/admin/audit-log' => 'Audit Log',
                '/admin/platform-settings' => 'Settings',
            ],
        ));
    }

    private function escape(string $value): string
    {
        return AdminLayout::escape($value);
    }
}

// End of file.
