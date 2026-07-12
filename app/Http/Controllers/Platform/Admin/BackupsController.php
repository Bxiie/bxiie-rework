<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Http\View\ErrorPage;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Membership\Roles;
use App\Platform\Operations\OperationsTaskLauncher;
use App\Support\Flash\FlashMessages;
use App\Support\Security\CsrfTokenService;

/** Displays backup status and starts allow-listed backup jobs. */
final class BackupsController
{
    private const STATUS_DIR = '/var/lib/artsfolio/backup-status';

    public function __construct(
        private readonly RequirePlatformRole $roles,
        private readonly CsrfTokenService $csrf,
        private readonly AuditLogRepository $auditLog,
        private readonly OperationsTaskLauncher $launcher,
    ) {
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $csrf = AdminLayout::escape($this->csrf->getOrCreate());
        $hourly = $this->readStatus('hourly.json');
        $weekly = $this->readStatus('weekly.json');
        $monthly = $this->readStatus('monthly.json');$body = '<p class="admin-notice">These controls queue systemd jobs. Refresh after the job finishes to see its recorded result.</p>'
            . '<div class="admin-grid-3">'
            . $this->jobCard('Hourly off-site backup', 'backup', 'artsfolio-backup.service', $hourly, $csrf, 'Run backup now')
            . $this->jobCard('Weekly repository verification', 'integrity-check', 'artsfolio-backup-weekly-check.service', $weekly, $csrf, 'Run verification now')
            . $this->jobCard('Monthly restore test', 'restore-test', 'artsfolio-backup-monthly-restore.service', $monthly, $csrf, 'Run restore test now')
            . '</div>'
            . '<h2>Backup details</h2>'
            . $this->detailTable('Latest hourly backup', $hourly)
            . $this->detailTable('Latest integrity check', $weekly)
            . $this->detailTable('Latest restore test', $monthly)
            . '<h2>Schedules</h2>' . $this->timerTable();

        return Response::html(AdminLayout::render(title: 'Backups | Platform Admin', body: $body, active: 'backups'));
    }

    public function action(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform owner or administrator access required.'), 403);
        }
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::invalidCsrf();
        }

        $task = trim((string) ($_POST['backup_action'] ?? ''));
        $labels = ['backup' => 'Off-site backup', 'integrity-check' => 'Repository verification', 'restore-test' => 'Restore test'];
        if (!isset($labels[$task])) {
            return Response::html('<h1>Invalid backup action</h1>', 422);
        }

        $result = $this->launcher->start($task);
        $this->auditLog->record(
            'platform.backup.manual_start',
            null,
            isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null,
            'operations_task',
            $task,
            ['queued' => $result['ok'], 'exit_code' => $result['exit_code'], 'output' => mb_substr($result['output'], 0, 1000)],
            $request->server('REMOTE_ADDR'),
        );

        if ($result['ok']) {
            FlashMessages::success($labels[$task] . ' was queued.');
        } else {
            FlashMessages::error($labels[$task] . ' could not be queued: ' . $result['output']);
        }

        return new Response('', 302, ['Location' => '/platform/admin/backups']);
    }

    /** @return array<string, mixed> */
    private function readStatus(string $filename): array
    {
        $path = self::STATUS_DIR . '/' . $filename;
        if (!is_readable($path)) {
            return ['status' => 'unavailable', 'detail' => 'No readable result exists at ' . $path . '.'];
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : ['status' => 'invalid', 'detail' => 'The status file is not valid JSON.'];
    }

    /** @param array<string, mixed> $status */
    private function jobCard(string $title, string $action, string $service, array $status, string $csrf, string $button): string
    {
        return '<section class="admin-card"><h2>' . AdminLayout::escape($title) . '</h2>'
            . '<p><strong>Recorded result:</strong> ' . AdminLayout::escape(strtoupper((string) ($status['status'] ?? 'unavailable'))) . '</p>'
            . '<p><strong>Checked:</strong> ' . AdminLayout::escape($this->statusTime($status)) . '</p>'
            . '<p><strong>Service:</strong> ' . AdminLayout::escape($this->serviceState($service)) . '</p>'
            . '<form method="post" action="/platform/admin/backups/action">'
            . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
            . '<input type="hidden" name="backup_action" value="' . AdminLayout::escape($action) . '">'
            . '<button type="submit">' . AdminLayout::escape($button) . '</button></form></section>';
    }

    /** @param array<string, mixed> $data */
    private function detailTable(string $title, array $data): string
    {
        $rows = '';
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_SLASHES);
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif ($value === null) {
                $value = '';
            }

            if (
                is_string($value)
                && ($key === 'checked_at' || str_ends_with((string) $key, '_at'))
            ) {
                $value = $this->displayUtcTime($value);
            }

            $rows .= '<tr><th>' . AdminLayout::escape(ucwords(str_replace('_', ' ', (string) $key))) . '</th>'
                . '<td><pre style="white-space:pre-wrap;margin:0">' . AdminLayout::escape((string) $value) . '</pre></td></tr>';
        }
        return '<h3>' . AdminLayout::escape($title) . '</h3><table class="admin-table"><tbody>' . $rows . '</tbody></table>';
    }

    private function serviceState(string $unit): string
    {
        $output = [];
        $code = 0;
        exec('/usr/bin/systemctl show ' . escapeshellarg($unit) . ' --property=ActiveState,SubState,Result --value 2>/dev/null', $output, $code);
        return $code === 0 && $output !== [] ? implode(' / ', array_filter(array_map('trim', $output))) : 'unavailable';
    }

    /** @param array<string, mixed> $status */
    private function statusTime(array $status): string
    {
        $raw = trim((string) ($status['checked_at'] ?? ''));

        return $raw === '' ? 'Never' : $this->displayUtcTime($raw);
    }

    /**
     * Backup status JSON stores ISO-8601 timestamps in UTC.
     */
    private function displayUtcTime(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        try {
            $value = new \DateTimeImmutable($raw, new \DateTimeZone('UTC'));
            $timezone = new \DateTimeZone($this->displayTimezone());
        } catch (\Throwable) {
            return $raw;
        }

        return $value->setTimezone($timezone)->format('M j, Y g:i:s A T');
    }

    /**
     * systemd supplies human-readable times with their source offset or zone.
     */
    private function displaySystemTime(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '' || in_array(strtolower($raw), ['n/a', 'unavailable'], true)) {
            return $raw !== '' ? $raw : 'unavailable';
        }

        try {
            $value = new \DateTimeImmutable($raw);
            $timezone = new \DateTimeZone($this->displayTimezone());
        } catch (\Throwable) {
            return $raw;
        }

        return $value->setTimezone($timezone)->format('M j, Y g:i:s A T');
    }

    /**
     * Bootstrap stores the signed-in user's configured IANA zone here.
     */
    private function displayTimezone(): string
    {
        $timezone = (string) ($GLOBALS['artsfolio_user_timezone'] ?? date_default_timezone_get());

        try {
            new \DateTimeZone($timezone);
            return $timezone;
        } catch (\Throwable) {
            return 'UTC';
        }
    }

    private function timerTable(): string
    {
        $timers = [
            'artsfolio-backup.timer' => 'Hourly backup',
            'artsfolio-backup-weekly-check.timer' => 'Weekly integrity check',
            'artsfolio-backup-monthly-restore.timer' => 'Monthly restore test',
        ];
        $rows = '';
        foreach ($timers as $unit => $label) {
            $output = [];
            $code = 0;
            exec('/usr/bin/systemctl show ' . escapeshellarg($unit) . ' --property=ActiveState,NextElapseUSecRealtime,LastTriggerUSec --value 2>/dev/null', $output, $code);
            $values = array_values(array_pad($output, 3, 'unavailable'));
            $rows .= '<tr><td>' . AdminLayout::escape($label) . '</td><td><code>' . AdminLayout::escape($unit) . '</code></td>'
                . '<td>' . AdminLayout::escape(trim((string) $values[0])) . '</td><td>' . AdminLayout::escape($this->displaySystemTime((string) $values[1])) . '</td>'
                . '<td>' . AdminLayout::escape($this->displaySystemTime((string) $values[2])) . '</td></tr>';
        }
        return '<table class="admin-table"><thead><tr><th>Schedule</th><th>Timer</th><th>State</th><th>Next</th><th>Last</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    }
}

// End of file.
