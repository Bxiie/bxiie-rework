<?php

declare(strict_types=1);

/**
 * Claims and sends one queued email outbox row using the configured email sender.
 */

use App\Platform\Email\EmailOutboxRepository;
use App\Platform\Email\EmailSenderFactory;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require_once $root . '/scripts/workers/heartbeat.php';
require $root . '/bootstrap/app.php';
artsfolio_worker_heartbeat('email-run-once', 'alive', ['entrypoint' => 'scripts/workers/email_run_once.php']);

$outbox = new EmailOutboxRepository(Database::connect($root));
$sender = EmailSenderFactory::fromEnvironment();

$email = $outbox->claimNext();

if (!$email) {
    echo "No queued emails available.\n";
    exit(0);
}

try {
    echo $sender->send($email) . PHP_EOL;
    $outbox->markSent((int) $email['id']);
    echo "Marked email {$email['id']} as sent.\n";
} catch (\Throwable $e) {
    $outbox->markFailed((int) $email['id'], $e->getMessage());
    fwrite(STDERR, "Failed email {$email['id']}: {$e->getMessage()}\n");
    exit(1);
}

// End of file.
