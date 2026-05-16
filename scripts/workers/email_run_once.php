<?php

declare(strict_types=1);

/**
 * Claims and dry-runs one queued email outbox row.
 */

use App\Platform\Email\DryRunEmailSender;
use App\Platform\Email\EmailOutboxRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$outbox = new EmailOutboxRepository(Database::connect($root));
$sender = new DryRunEmailSender();

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
