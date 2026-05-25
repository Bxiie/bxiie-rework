<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;


use App\Http\View\ErrorPage;
use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Membership\Roles;
use App\Platform\Workers\WorkerHeartbeatRepository;

/**
 * Handles platform-admin worker heartbeat screen.
 */
final class WorkersController
{
    public function __construct(
        private readonly RequirePlatformRole $roles,
        private readonly WorkerHeartbeatRepository $heartbeats,
    ) {
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $rows = '';

        foreach ($this->heartbeats->latest(100) as $worker) {
            $detailsPreview = mb_substr((string) ($worker['details'] ?? ''), 0, 180);
            $effectiveStatus = $this->effectiveStatus((string) $worker['status'], (string) $worker['last_seen_at']);
            $ageSeconds = $this->ageSeconds((string) $worker['last_seen_at']);

            $rows .= '<tr>'
                . '<td>' . AdminLayout::escape((string) $worker['worker_name']) . '</td>'
                . '<td>' . AdminLayout::escape((string) ($worker['host_name'] ?? '')) . '</td>'
                . '<td>' . AdminLayout::escape((string) ($worker['process_id'] ?? '')) . '</td>'
                . '<td>' . AdminLayout::escape($effectiveStatus) . '</td>'
                . '<td>' . AdminLayout::escape((string) $worker['last_seen_at']) . '</td>'
                . '<td>' . AdminLayout::escape((string) $ageSeconds) . '</td>'
                . '<td><code>' . AdminLayout::escape($detailsPreview) . '</code></td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="7">No worker heartbeats found.</td></tr>';
        }

        return Response::html(AdminLayout::render(
            title: 'Workers | Platform Admin',
            body: <<<HTML
<table class="admin-table">
    <thead>
        <tr>
            <th>Worker</th>
            <th>Host</th>
            <th>PID</th>
            <th>Status</th>
            <th>Last Seen</th>
            <th>Age Seconds</th>
            <th>Details</th>
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
                '/admin/domains' => 'Domains',
                '/admin/jobs' => 'Jobs',
                '/admin/workers' => 'Workers',
                '/admin/email-outbox' => 'Email Outbox',
                '/admin/audit-log' => 'Audit Log',
                '/admin/platform-settings' => 'Settings',
                '/admin/routes' => 'Routes',
            ],
        ));
    }

    private function effectiveStatus(string $storedStatus, string $lastSeenAt): string
    {
        $ageSeconds = $this->ageSeconds($lastSeenAt);

        if ($ageSeconds > 300) {
            return 'stale';
        }

        return $storedStatus;
    }

    private function ageSeconds(string $lastSeenAt): int
    {
        $timestamp = strtotime($lastSeenAt);

        if ($timestamp === false) {
            return 999999;
        }

        return max(0, time() - $timestamp);
    }
}

// End of file.
