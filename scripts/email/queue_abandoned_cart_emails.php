#!/usr/bin/php
<?php

/**
 * Queues 1-day, 3-day, and 7-day abandoned-cart reminder emails.
 *
 * This script queues email_outbox rows only. Delivery is handled by the normal
 * email worker, so smoke/preflight tests do not send SMTP mail.
 */

declare(strict_types=1);

use App\Support\Database;
use App\Tenant\Sales\AbandonedCartEmailQueueService;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$limit = max(1, min(1000, (int) (getenv('ARTSFOLIO_ABANDONED_CART_LIMIT_PER_STAGE') ?: 200)));
$result = (new AbandonedCartEmailQueueService($pdo, $root))->queueDue($limit);

echo json_encode($result, JSON_PRETTY_PRINT) . "
";

// End of file.
