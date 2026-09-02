<?php

declare(strict_types=1);

// Regression coverage for the 2026-09-01 sales.inventory.release_expired
// deadlock incident (job 38564, SQLSTATE[40001]).
//
// Two independent defects combined to produce that incident:
//   1. SalesRepository::releaseExpiredReservations() had no retry for MariaDB
//      deadlocks, even though its UPDATEs range-scan the same
//      (status, expires_at) index that checkout's
//      expireReservationsWithinTransaction() scans on every checkout attempt,
//      making an occasional InnoDB gap-lock collision expected under load.
//   2. scripts/workers/run_once.php only re-enqueued the next cycle of a
//      self-requeuing job (analytics.rollup, sales.cart.queue_abandoned_reminders,
//      sales.inventory.release_expired) on the success path, so a single
//      transient failure permanently stopped that recurring job until an
//      admin manually clicked Requeue.
//
// Both checks below would have failed against the pre-fix source.

$root = dirname(__DIR__, 2);
$failures = [];

$repo = file_get_contents($root . '/app/Tenant/Sales/SalesRepository.php') ?: '';

foreach ([
    'private function withDeadlockRetry',
    "\$e->getCode() !== '40001'",
] as $needle) {
    if (!str_contains($repo, $needle)) {
        $failures[] = "SalesRepository missing {$needle}";
    }
}

if (!preg_match('/public function releaseExpiredReservations\(\): int\s*\{\s*(?:\/\/[^\n]*\n\s*)*return \$this->withDeadlockRetry\(/', $repo)) {
    $failures[] = 'releaseExpiredReservations() no longer wraps its transaction in withDeadlockRetry().';
}

$worker = file_get_contents($root . '/scripts/workers/run_once.php') ?: '';

$selfRequeuingJobTypes = [
    'analytics.rollup',
    'sales.cart.queue_abandoned_reminders',
    'sales.inventory.release_expired',
];

foreach ($selfRequeuingJobTypes as $jobType) {
    $caseNeedle = "case '{$jobType}':";
    $caseStart = strpos($worker, $caseNeedle);
    if ($caseStart === false) {
        $failures[] = "run_once.php missing {$caseNeedle}";
        continue;
    }

    $nextCase = strpos($worker, "\n        case '", $caseStart + strlen($caseNeedle));
    $nextDefault = strpos($worker, "\n        default:", $caseStart + strlen($caseNeedle));
    $blockEnd = match (true) {
        $nextCase === false && $nextDefault === false => strlen($worker),
        $nextCase === false => $nextDefault,
        $nextDefault === false => $nextCase,
        default => min($nextCase, $nextDefault),
    };
    $block = substr($worker, $caseStart, $blockEnd - $caseStart);

    $finallyPos = strpos($block, 'finally {');
    $enqueuePos = strpos($block, "enqueueSingleton('{$jobType}'");

    if ($finallyPos === false) {
        $failures[] = "run_once.php {$jobType} case has no finally block re-enqueuing the next cycle.";
    } elseif ($enqueuePos === false) {
        $failures[] = "run_once.php {$jobType} case is missing its enqueueSingleton call.";
    } elseif ($enqueuePos < $finallyPos) {
        $failures[] = "run_once.php {$jobType} re-enqueues before entering the finally block (won't run on failure).";
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Queue deadlock recovery static checks passed.\n";

// End of file.
