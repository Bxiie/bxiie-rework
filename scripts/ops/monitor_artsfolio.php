<?php

declare(strict_types=1);

use App\Platform\Monitoring\HealthMetric;
use App\Platform\Monitoring\OperationsMonitor;
use App\Platform\Monitoring\OperationsMonitorNotifier;
use App\Platform\Monitoring\OperationsMonitorRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';


/** @return array<string,string> */
function artsfolioComponentStates(App\Platform\Monitoring\HealthReport $report): array
{
    $states = [];
    foreach ($report->metrics as $metric) {
        if (str_starts_with($metric->name, 'service.')) {
            $states[$metric->name] = $metric->status === App\Platform\Monitoring\HealthMetric::OK ? 'running' : 'stopped';
            continue;
        }
        if (str_starts_with($metric->name, 'worker.') && str_ends_with($metric->name, '.heartbeat_age_seconds')) {
            $states[$metric->name] = $metric->status === App\Platform\Monitoring\HealthMetric::OK ? 'running' : 'stopped';
        }
    }
    ksort($states);
    return $states;
}

function artsfolioFriendlyComponentName(string $metricName): string
{
    if (str_starts_with($metricName, 'service.')) {
        return str_replace(['service.', '_service', '_'], ['', '', ' '], $metricName);
    }
    if (preg_match('/^worker\.([^.]*)\.heartbeat_age_seconds$/', $metricName, $matches) === 1) {
        return str_replace('-', ' ', $matches[1]) . ' worker';
    }
    return $metricName;
}

function artsfolioCurrentBootId(): string
{
    $linuxBootId = '/proc/sys/kernel/random/boot_id';
    if (is_readable($linuxBootId)) {
        return trim((string) file_get_contents($linuxBootId));
    }
    $bootTime = trim((string) shell_exec('sysctl -n kern.boottime 2>/dev/null'));
    if ($bootTime !== '') {
        return hash('sha256', $bootTime);
    }
    return '';
}

$options = getopt('', ['json', 'no-email', 'force-report', 'trouble-only', 'component-started:', 'notification-only']);
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
$currentBootId = artsfolioCurrentBootId();
$previousBootId = (string) ($state['last_boot_id'] ?? '');
$restartDetected = $currentBootId !== '' && $previousBootId !== '' && !hash_equals($previousBootId, $currentBootId);
$currentComponentStates = artsfolioComponentStates($report);
$previousComponentStatesRaw = (string) ($state['last_component_states_json'] ?? '');
$previousComponentStates = [];
if ($previousComponentStatesRaw !== '') {
    $decodedComponentStates = json_decode($previousComponentStatesRaw, true);
    if (is_array($decodedComponentStates)) {
        $previousComponentStates = array_map('strval', $decodedComponentStates);
    }
}
$componentBaselineExists = $previousComponentStates !== [];
$startedComponents = [];
if ($componentBaselineExists) {
    foreach ($currentComponentStates as $componentName => $componentStatus) {
        if ($componentStatus === 'running' && ($previousComponentStates[$componentName] ?? 'unknown') !== 'running') {
            $startedComponents[] = artsfolioFriendlyComponentName($componentName);
        }
    }
}
$explicitStartedComponents = [];
if (isset($options['component-started'])) {
    $rawExplicitComponents = is_array($options['component-started'])
        ? implode(',', array_map('strval', $options['component-started']))
        : (string) $options['component-started'];
    foreach (explode(',', $rawExplicitComponents) as $componentName) {
        $componentName = trim($componentName);
        if ($componentName !== '') {
            $explicitStartedComponents[] = $componentName;
        }
    }
    $explicitStartedComponents = array_values(array_unique($explicitStartedComponents));
}
if ($explicitStartedComponents !== []) {
    $startedComponents = $explicitStartedComponents;
}
$componentStartDetected = $startedComponents !== [];
$currentComponentStatesJson = json_encode($currentComponentStates, JSON_THROW_ON_ERROR);
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

if (!isset($options['no-email'])) {
    $notifications = [];
    if ($restartDetected) {
        $notifications[] = ['kind' => 'restart', 'context' => []];
    }
    if ($componentStartDetected) {
        $notifications[] = ['kind' => 'component_start', 'context' => ['started_components' => $startedComponents]];
    }
    if ($shouldAlert) {
        $notifications[] = ['kind' => (string) $notificationKind, 'context' => []];
    } elseif ($scheduledKind !== null) {
        $notifications[] = ['kind' => 'scheduled', 'context' => []];
    }

    foreach ($notifications as $notification) {
        try {
            $notifier = new OperationsMonitorNotifier($pdo, $repository);
            $delivery = $notifier->send($report, $notification['kind'], $notification['context']);
            $recipientCount = count($delivery['results'] ?? []);
            if (($delivery['dry_run'] ?? false) === true) {
                echo 'Health email dry run generated for ' . $recipientCount . " platform administrator(s); no message was sent.\n";
            } else {
                echo 'Health email sent to ' . $recipientCount . " platform administrator(s).\n";
            }
        } catch (Throwable $e) {
            fwrite(STDERR, 'Health email delivery failed: ' . $e->getMessage() . "\n");
            $bootIdForState = $restartDetected ? $previousBootId : ($currentBootId !== '' ? $currentBootId : $previousBootId);
            $componentStatesForState = $componentStartDetected ? $previousComponentStatesRaw : $currentComponentStatesJson;
            $repository->updateState($report, $lastAlertAt, $morningDate, $eveningDate, $bootIdForState, $componentStatesForState);
            exit(2);
        }
    }

    if ($shouldAlert && $notifications !== []) {
        $lastAlertAt = gmdate('Y-m-d H:i:s');
    }
    if ($scheduledKind !== null && $notifications !== []) {
        $morningDate = $nextMorningDate;
        $eveningDate = $nextEveningDate;
    }
}

$bootIdForState = (isset($options['no-email']) && $restartDetected) ? $previousBootId : ($currentBootId !== '' ? $currentBootId : $previousBootId);
$componentStatesForState = (isset($options['no-email']) && $componentStartDetected) ? $previousComponentStatesRaw : $currentComponentStatesJson;
$repository->updateState($report, $lastAlertAt, $morningDate, $eveningDate, $bootIdForState, $componentStatesForState);

if (isset($options['notification-only'])) {
    exit(0);
}

exit($currentStatus === HealthMetric::CRIT ? 2 : ($currentStatus === HealthMetric::WARN ? 1 : 0));

// End of file.
