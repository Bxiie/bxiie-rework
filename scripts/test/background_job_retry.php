<?php

declare(strict_types=1);

/**
 * Functional check for the configurable background-job auto-retry budget:
 * BackgroundJobRepository::markFailed() requeues with backoff while attempts
 * remain under max_attempts, and only sets the terminal 'failed' status
 * (which queue.jobs.failed in OperationsMonitor counts) once exhausted.
 *
 * Not part of scripts/test/preflight.sh: production's PHP build has no
 * ext-pdo_sqlite. scripts/test/background_job_concurrency_static.php carries
 * the dependency-free regression markers for this change instead.
 */

use App\Platform\Jobs\BackgroundJobRepository;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

function check(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

// --- Pure-function checks: no DB involved ---------------------------------

$resolveMaxAttempts = new ReflectionMethod(BackgroundJobRepository::class, 'resolveMaxAttempts');

putenv('ARTSFOLIO_BACKGROUND_MAX_ATTEMPTS');
check($resolveMaxAttempts->invoke(null, 7) === 7, 'An explicit per-call max attempts value must win');
check($resolveMaxAttempts->invoke(null, 0) === 1, 'max attempts must never resolve below 1');
check($resolveMaxAttempts->invoke(null, null) === 5, 'With nothing configured, the built-in default (5) must apply');

putenv('ARTSFOLIO_BACKGROUND_MAX_ATTEMPTS=9');
check($resolveMaxAttempts->invoke(null, null) === 9, 'ARTSFOLIO_BACKGROUND_MAX_ATTEMPTS must be used when no explicit value is given');
check($resolveMaxAttempts->invoke(null, 2) === 2, 'An explicit value must still win over the env var');
putenv('ARTSFOLIO_BACKGROUND_MAX_ATTEMPTS');

$pdoForReflection = new PDO('sqlite::memory:');
$repoForReflection = new BackgroundJobRepository($pdoForReflection);
$retryDelaySeconds = new ReflectionMethod($repoForReflection, 'retryDelaySeconds');

$expectedDelays = [1 => 60, 2 => 120, 3 => 240, 4 => 480, 5 => 960];
foreach ($expectedDelays as $attemptsSoFar => $expected) {
    $actual = $retryDelaySeconds->invoke($repoForReflection, $attemptsSoFar);
    check($actual === $expected, "retryDelaySeconds({$attemptsSoFar}) expected {$expected}s, got {$actual}s");
}
check($retryDelaySeconds->invoke($repoForReflection, 20) === 1800, 'Backoff must cap at 1800s even for a very high attempt count');

// --- markFailed(): real SELECT+UPDATE logic against a seeded row ----------

final class RetryTestPdo extends Pdo\Sqlite
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        // Only the MariaDB-specific relative-time expression needs
        // translating; everything else (columns, params, branching) runs
        // unchanged against the real markFailed() implementation.
        $query = str_replace(
            "DATE_ADD(CURRENT_TIMESTAMP, INTERVAL :delay_seconds SECOND)",
            "datetime(CURRENT_TIMESTAMP, '+' || :delay_seconds || ' seconds')",
            $query,
        );

        return parent::prepare($query, $options);
    }
}

$pdo = new RetryTestPdo('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec(
    "CREATE TABLE background_jobs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        status TEXT NOT NULL,
        attempts INTEGER NOT NULL,
        max_attempts INTEGER NOT NULL,
        available_at TEXT,
        started_at TEXT,
        failed_at TEXT,
        last_error TEXT,
        updated_at TEXT
    );"
);
$repo = new BackgroundJobRepository($pdo);

function seedJob(PDO $pdo, int $attempts, int $maxAttempts, string $status = 'running'): int
{
    $pdo->exec("INSERT INTO background_jobs (status, attempts, max_attempts, started_at) VALUES ('{$status}', {$attempts}, {$maxAttempts}, CURRENT_TIMESTAMP)");

    return (int) $pdo->lastInsertId();
}

function fetchJob(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM background_jobs WHERE id = :id');
    $stmt->execute(['id' => $id]);

    return $stmt->fetch();
}

// 1. Attempts remain under budget: requeue with backoff, not terminal.
$jobId = seedJob($pdo, attempts: 1, maxAttempts: 3);
$exhausted = $repo->markFailed($jobId, 'transient network error');
check($exhausted === false, 'A job under its max_attempts budget must not be reported as exhausted');
$row = fetchJob($pdo, $jobId);
check($row['status'] === 'queued', 'A retried job must go back to queued, not failed');
check($row['started_at'] === null, 'A retried job must clear started_at so it reads as not-yet-run again');
check($row['last_error'] === 'transient network error', 'The failure message must be recorded even on a retry');
check($row['failed_at'] === null, 'A retried (non-terminal) job must not get a failed_at timestamp');

// 2. Attempts exhausted: terminal failure.
$jobId = seedJob($pdo, attempts: 3, maxAttempts: 3);
$exhausted = $repo->markFailed($jobId, 'out of retries');
check($exhausted === true, 'A job at its max_attempts budget must be reported as exhausted');
$row = fetchJob($pdo, $jobId);
check($row['status'] === 'failed', 'An exhausted job must be terminally failed');
check($row['failed_at'] !== null, 'A terminal failure must record failed_at (what queue.jobs.failed counts on)');

// 3. Attempts already past max (e.g. max_attempts lowered after the job was
//    enqueued): must still terminate rather than loop forever.
$jobId = seedJob($pdo, attempts: 5, maxAttempts: 3);
$exhausted = $repo->markFailed($jobId, 'still broken');
check($exhausted === true, 'A job already past its max_attempts budget must terminate, not retry indefinitely');

// 4. No matching running row (e.g. recovered by requeueRunningOlderThanMinutes
//    in between): must report terminal rather than claim a phantom retry.
$exhausted = $repo->markFailed(999999, 'does not exist');
check($exhausted === true, 'markFailed on a job that is no longer running must report terminal, not a misleading retry');

echo "Background job retry checks passed.\n";

// End of file.
