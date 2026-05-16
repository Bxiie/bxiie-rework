<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Email\EmailOutboxRepository;
use App\Platform\Membership\Roles;

/**
 * Handles platform-admin email outbox list screen.
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
            return Response::html('<h1>Forbidden</h1><p>Platform admin access required.</p>', 403);
        }

        $rows = '';

        foreach ($this->outbox->latest(50) as $email) {
            $rows .= '<tr>'
                . '<td>' . $this->escape((string) $email['id']) . '</td>'
                . '<td>' . $this->escape((string) $email['status']) . '</td>'
                . '<td>' . $this->escape((string) $email['recipient_email']) . '</td>'
                . '<td>' . $this->escape((string) $email['subject']) . '</td>'
                . '<td>' . $this->escape((string) ($email['template_key'] ?? '')) . '</td>'
                . '<td>' . $this->escape((string) $email['attempts']) . '</td>'
                . '<td>' . $this->escape((string) $email['created_at']) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="7">No email outbox rows found.</td></tr>';
        }

        return Response::html(<<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Email Outbox | Platform Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<h1>Email Outbox</h1>

<table border="1" cellpadding="6" cellspacing="0">
    <thead>
        <tr>
            <th>ID</th>
            <th>Status</th>
            <th>Recipient</th>
            <th>Subject</th>
            <th>Template</th>
            <th>Attempts</th>
            <th>Created</th>
        </tr>
    </thead>
    <tbody>
        {$rows}
    </tbody>
</table>

<p><a href="/admin">Back to platform admin</a></p>
</body>
</html>
HTML);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
