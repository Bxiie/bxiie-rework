<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'scripts/ops/monitor_artsfolio.php' => [
        'artsfolioComponentStates',
        'artsfolioFriendlyComponentName',
        'component_start',
        'started_components',
        'last_component_states_json',
    ],
    'app/Platform/Monitoring/OperationsMonitorNotifier.php' => [
        '[ArtsFolio COMPONENT STARTED]',
        'started_components',
    ],
    'app/Platform/Monitoring/HealthReport.php' => [
        'Application component started.',
        'started_components',
    ],
    'app/Platform/Monitoring/OperationsMonitorRepository.php' => [
        'last_component_states_json',
    ],
    'database/migrations/0046_operations_monitor_component_state.sql' => [
        'last_component_states_json',
    ],
    'scripts/database/check_migration_integrity.php' => [
        'migration_recorded_but_column_missing',
        '0046_operations_monitor_component_state.sql',
    ],
];

$errors = [];
foreach ($checks as $relative => $needles) {
    $path = $root . '/' . $relative;
    $content = is_file($path) ? (string) file_get_contents($path) : '';
    if ($content === '') {
        $errors[] = "Missing file: {$relative}";
        continue;
    }
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $errors[] = "Missing {$needle} in {$relative}";
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

echo "Phase 9 component-start notification static checks passed.\n";
