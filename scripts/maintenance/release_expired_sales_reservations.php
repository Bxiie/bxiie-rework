<?php

declare(strict_types=1);

use App\Support\Database;
use App\Tenant\Sales\SalesRepository;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$released = (new SalesRepository($pdo))->releaseExpiredReservations();

echo json_encode(['released_reservations' => $released], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;

// End of file.
