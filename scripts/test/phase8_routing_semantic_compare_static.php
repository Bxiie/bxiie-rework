<?php

declare(strict_types=1);

/**
 * Static coverage for semantic route inventory comparison.
 */

$root = dirname(__DIR__, 2);
$phase8 = $root . '/scripts/test/phase8_routing_static.php';

if (!is_file($phase8)) {
    fwrite(STDERR, "Missing phase8 routing static test.\n");
    exit(1);
}

$source = file_get_contents($phase8);

$required = [
    'normalizeRouteInventory',
    'ksort($route)',
    'ksort($normalized)',
    'Added routes:',
    'Removed routes:',
    'Changed routes:',
    'route_inventory.php',
    'route_inventory.json',
];

foreach ($required as $needle) {
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, "Missing semantic route comparison marker: {$needle}\n");
        exit(1);
    }
}

if (str_contains($source, 'trim($actualJson) !== trim(')) {
    fwrite(STDERR, "Phase 8 still appears to use byte-level JSON comparison.\n");
    exit(1);
}

echo "Semantic route inventory comparison static checks passed.\n";

// End of file.
