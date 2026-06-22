<?php

declare(strict_types=1);

use App\Platform\Monitoring\HealthMetric;
use App\Platform\Monitoring\OperationsMonitor;
use App\Platform\Monitoring\OperationsMonitorNotifier;
use App\Platform\Monitoring\OperationsMonitorRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$options = getopt('', ['json', 'no-email', 'force-report', 'trouble-only']);
$lockPath = getenv('ARTSFOLIO_MONITOR_LOCK_FILE') ?: '/tmp/artsfolio-monitor.lock';
$lock = fopen($lockPath, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Another ArtsFolio monitor process is already running.\n");
    exit(0);
}

$pdo = Database::connect($root);
$monitor = new OperationsMonitor($pdo, $root);
$repository = new OperationsMonitorRepository($pdo);
$report = $monitor->run();
$runId = $repository->recordRun($report);

if (isset($options['json'])) {
    echo json_encode($report->toArray() + ['run_id' => $runId], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    echo $report->toText(isset($options['trouble-only']));
    echo "Monitor run ID: {$runId}\n";
}

$state = $repository->state();
$timezone = new DateTimeZone(getenv('ARTSFOLIO_MONITOR_TIMEZONE') ?: 'America/New_York');
$nowLocal = new DateTimeImmutable('now', $timezone);
$today = $nowLocal->format('Y-m-d');
$hourMinute = $nowLocal->format('H:i');
$morningDate = $state['last_morning_report_date'] ?? null;
$eveningDate = $state['last_evening_report_date'] ?? null;
$nextMorningDate = $morningDate;
$nextEveningDate = $eveningDate;
$lastAlertAt = $state['last_alert_at'] ?? null;

$scheduledKind = null;
if (($hourMinute >= '07:15' && $hourMinute <= '07:19') && $morningDate !== $today) {
    $scheduledKind = 'morning';
    $nextMorningDate = $today;
}
if (($hourMinute >= '19:15' && $hourMinute <= '19:19') && $eveningDate !== $today) {
    $scheduledKind = 'evening';
    $nextEveningDate = $today;
}
if (isset($options['force-report'])) {
    $scheduledKind = 'forced';
}

$currentStatus = $report->overallStatus();
$previousStatus = (string) ($state['last_status'] ?? 'UNKNOWN');
$fingerprintChanged = !hash_equals((string) ($state['last_fingerprint'] ?? ''), $report->fingerprint());
$lastAlertEpoch = $lastAlertAt ? strtotime((string) $lastAlertAt . ' UTC') : false;
$minutesSinceAlert = $lastAlertEpoch === false ? PHP_INT_MAX : (int) floor((time() - $lastAlertEpoch) / 60);
$warnReminderMinutes = max(15, (int) (getenv('ARTSFOLIO_MONITOR_WARN_REMINDER_MINUTES') ?: 360));
$critReminderMinutes = max(5, (int) (getenv('ARTSFOLIO_MONITOR_CRIT_REMINDER_MINUTES') ?: 60));
$shouldAlert = false;
$notificationKind = null;

if (in_array($currentStatus, [HealthMetric::WARN, HealthMetric::CRIT], true)) {
    $reminderDue = $currentStatus === HealthMetric::CRIT
        ? $minutesSinceAlert >= $critReminderMinutes
        : $minutesSinceAlert >= $warnReminderMinutes;
    $shouldAlert = $previousStatus !== $currentStatus || $fingerprintChanged || $reminderDue;
    $notificationKind = 'alert';
} elseif (in_array($previousStatus, [HealthMetric::WARN, HealthMetric::CRIT], true)) {
    $shouldAlert = true;
    $notificationKind = 'recovery';
}

if (!isset($options['no-email']) && ($scheduledKind !== null || $shouldAlert)) {
    $kind = $shouldAlert ? $notificationKind : 'scheduled';
    try {
        $notifier = new OperationsMonitorNotifier($pdo, $repository);
        $delivery = $notifier->send($report, (string) $kind);
        $recipientCount = count($delivery['results'] ?? []);
        if (($delivery['dry_run'] ?? false) === true) {
            echo 'Health email dry run generated for ' . $recipientCount . " platform administrator(s); no message was sent.\n";
        } else {
            echo 'Health email sent to ' . $recipientCount . " platform administrator(s).\n";
        }
        if ($shouldAlert) {
            $lastAlertAt = gmdate('Y-m-d H:i:s');
        }
        if ($scheduledKind !== null) {
            $morningDate = $nextMorningDate;
            $eveningDate = $nextEveningDate;
        }
    } catch (Throwable $e) {
        fwrite(STDERR, 'Health email delivery failed: ' . $e->getMessage() . "\n");
        $repository->updateState($report, $lastAlertAt, $morningDate, $eveningDate);
        exit(2);
    }
}

$repository->updateState($report, $lastAlertAt, $morningDate, $eveningDate);

exit($currentStatus === HealthMetric::CRIT ? 2 : ($currentStatus === HealthMetric::WARN ? 1 : 0));

// End of file.
