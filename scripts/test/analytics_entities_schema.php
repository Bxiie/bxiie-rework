<?php

declare(strict_types=1);

use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$stmt = $pdo->query("SHOW COLUMNS FROM analytics_events WHERE Field IN ('entity_type', 'entity_id')");
$columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

$missing = array_values(array_diff(['entity_type', 'entity_id'], $columns));

if ($missing) {
    fwrite(STDERR, 'Missing analytics entity columns: ' . implode(', ', $missing) . PHP_EOL);
    exit(1);
}

echo json_encode(['ok' => true, 'columns' => $columns], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
