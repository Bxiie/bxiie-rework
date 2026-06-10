<?php

declare(strict_types=1);

/**
 * Prints recent email outbox rows for manual development verification.
 */

use App\Platform\Email\EmailOutboxRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$outbox = new EmailOutboxRepository(Database::connect($root));

echo json_encode($outbox->latest(20), JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
