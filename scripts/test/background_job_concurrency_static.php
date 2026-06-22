<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'app/Platform/Jobs/BackgroundJobRepository.php' => [
        'FOR UPDATE SKIP LOCKED',
        "GET_LOCK(:lock_name, 0)",
        "IS_FREE_LOCK(CONCAT('artsfolio-background-job:', id)) = 1",
        'DATE_SUB(CURRENT_TIMESTAMP, INTERVAL :minutes MINUTE)',
        'enqueueSingleton(',
        'hash(\'sha1\', $jobType)',
        "status IN ('queued', 'running')",
        'releaseExecutionLock(',
    ],
    'scripts/workers/run_once.php' => [
        "enqueueSingleton('analytics.rollup'",
        "enqueueSingleton('sales.inventory.release_expired'",
        '$jobs->releaseExecutionLock((int) $job[\'id\']);',
    ],
    'app/Platform/Monitoring/OperationsMonitor.php' => [
        "payment_status IN ('paid','complete','completed','succeeded','payment_succeeded')",
        'TIMESTAMPDIFF(MINUTE, MIN(available_at), CURRENT_TIMESTAMP)',
        'available_at <= CURRENT_TIMESTAMP',
        'DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 30 MINUTE)',
    ],
];

$errors = [];
foreach ($checks as $relative => $needles) {
    $path = $root . '/' . $relative;
    $content = is_file($path) ? (string) file_get_contents($path) : '';
    if ($content === '') {
        $errors[] = "Missing file: {$relative}";
        continue;
    }
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $errors[] = "Missing {$needle} in {$relative}";
        }
    }
}

$repository = (string) file_get_contents($root . '/app/Platform/Jobs/BackgroundJobRepository.php');
$monitor = (string) file_get_contents($root . '/app/Platform/Monitoring/OperationsMonitor.php');

foreach ([
    "DATE_SUB(UTC_TIMESTAMP(), INTERVAL :minutes MINUTE)",
    "Recovered stale running job at ', UTC_TIMESTAMP()",
] as $forbidden) {
    if (str_contains($repository, $forbidden)) {
        $errors[] = "Forbidden mixed-clock queue expression remains: {$forbidden}";
    }
}

foreach ([
    "FROM background_jobs WHERE status = 'queued' AND available_at <= UTC_TIMESTAMP()",
    "sales_orders WHERE status IN",
] as $forbidden) {
    if (str_contains($monitor, $forbidden)) {
        $errors[] = "Forbidden monitor expression remains: {$forbidden}";
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

echo "Background job clock and concurrency static checks passed.\n";

// End of file.
