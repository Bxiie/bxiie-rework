<?php

declare(strict_types=1);

use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';
$pdo = Database::connect($root);

$requirements = [
    'schema_migrations' => ['migration', 'checksum_sha256', 'applied_at'],
    'background_jobs' => ['status', 'available_at', 'updated_at'],
    'email_outbox' => ['status', 'available_at', 'updated_at'],
    'worker_heartbeats' => ['worker_name', 'last_seen_at', 'status'],
    'analytics_rollups_hourly' => ['bucket_start', 'event_count'],
    'tenant_directory_profiles' => ['tenant_id', 'is_listed', 'sort_name'],
    'sales_inventory_reservations' => ['order_id', 'artwork_id', 'status', 'expires_at'],
    'operations_monitor_runs' => ['overall_status', 'report_json', 'created_at'],
    'operations_monitor_state' => ['last_status', 'last_fingerprint', 'last_alert_at', 'last_boot_id', 'last_component_states_json'],
    'operations_monitor_metrics' => ['run_id', 'metric_name', 'metric_status', 'actual_value', 'actual_numeric', 'created_at'],
];

$problems = [];
foreach ($requirements as $table => $columns) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name");
    $stmt->execute(['table_name' => $table]);
    if ((int) $stmt->fetchColumn() === 0) {
        $problems[] = "Missing table: {$table}";
        continue;
    }
    foreach ($columns as $column) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name");
        $stmt->execute(['table_name' => $table, 'column_name' => $column]);
        if ((int) $stmt->fetchColumn() === 0) {
            $problems[] = "Missing column: {$table}.{$column}";
        }
    }
}

$result = ['ok' => $problems === [], 'problem_count' => count($problems), 'problems' => $problems];
echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
exit($problems === [] ? 0 : 1);
