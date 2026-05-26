<?php

declare(strict_types=1);

use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);

$requiredColumns = ['country', 'region', 'city'];
$stmt = $pdo->query('DESCRIBE analytics_events');
$columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
$missing = array_values(array_diff($requiredColumns, $columns));

$cacheExistsStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name'
);
$cacheExistsStmt->execute(['table_name' => 'analytics_ip_locations']);
$cacheExists = (int) $cacheExistsStmt->fetchColumn() > 0;

$eventsWithLocation = 0;
$locationRows = [];
if (!$missing) {
    $eventsWithLocation = (int) $pdo->query(
        "SELECT COUNT(*) FROM analytics_events WHERE COALESCE(country, '') <> '' OR COALESCE(region, '') <> '' OR COALESCE(city, '') <> ''"
    )->fetchColumn();

    $locationRows = $pdo->query(
        "SELECT COALESCE(country, '') AS country,
                COALESCE(region, '') AS region,
                COALESCE(city, '') AS city,
                COUNT(*) AS total
           FROM analytics_events
          WHERE COALESCE(country, '') <> '' OR COALESCE(region, '') <> '' OR COALESCE(city, '') <> ''
          GROUP BY country, region, city
          ORDER BY total DESC
          LIMIT 10"
    )->fetchAll(PDO::FETCH_ASSOC);
}

$result = [
    'ok' => !$missing && $cacheExists,
    'analytics_events_missing_columns' => $missing,
    'analytics_ip_locations_exists' => $cacheExists,
    'events_with_location' => $eventsWithLocation,
    'top_locations' => $locationRows,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (!$result['ok']) {
    exit(1);
}

// End of file.
