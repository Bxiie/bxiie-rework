<?php

/**
 * Claims and sends one queued email outbox row using platform email settings.
 */

declare(strict_types=1);

use App\Platform\Email\EmailOutboxRepository;
use App\Platform\Email\RecurringLifecycleEmailScheduler;
use App\Platform\Email\EmailSenderFactory;
use App\Platform\Settings\PlatformSettingsRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require_once $root . '/scripts/workers/heartbeat.php';
require $root . '/bootstrap/app.php';
$workerName = trim((string) (getenv('ARTSFOLIO_WORKER_NAME') ?: 'email-run-once'));
artsfolio_worker_heartbeat($workerName, 'alive', ['entrypoint' => 'scripts/workers/email_run_once.php']);

$pdo = Database::connect($root);
$outbox = new EmailOutboxRepository($pdo);
$staleMinutes = max(1, (int) (getenv('ARTSFOLIO_EMAIL_STALE_MINUTES') ?: 30));
$recovered = $outbox->requeueSendingOlderThanMinutes($staleMinutes);
if ($recovered > 0) {
    echo "Recovered {$recovered} stale email(s).\n";
}
$sender = EmailSenderFactory::fromPlatformSettings(new PlatformSettingsRepository($pdo));

$email = $outbox->claimNext();

if (!$email) {
    artsfolio_worker_heartbeat($workerName, 'idle', ['entrypoint' => 'scripts/workers/email_run_once.php']);
    echo "No queued emails available.\n";
    exit(0);
}

try {
    artsfolio_worker_heartbeat($workerName, 'running', ['email_id' => (int) $email['id']]);
    echo $sender->send($email) . PHP_EOL;
    $outbox->markSent((int) $email['id']);
    (new RecurringLifecycleEmailScheduler($pdo, new PlatformSettingsRepository($pdo)))->queueNext($email);
    artsfolio_worker_heartbeat($workerName, 'alive', ['last_email_id' => (int) $email['id']]);
    echo "Marked email {$email['id']} as sent.\n";
} catch (\Throwable $e) {
    $outbox->markFailed((int) $email['id'], $e->getMessage());
    artsfolio_worker_heartbeat($workerName, 'failed', ['email_id' => (int) $email['id'], 'error' => $e->getMessage()]);
    fwrite(STDERR, "Failed email {$email['id']}: {$e->getMessage()}\n");
    exit(1);
}

// End of file.
