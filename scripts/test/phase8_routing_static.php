<?php

declare(strict_types=1);

/**
 * Verifies that the committed route inventory snapshot matches the current
 * application route inventory.
 *
 * The comparison is intentionally semantic rather than byte-level so harmless
 * JSON formatting, associative-key ordering, or generator output ordering does
 * not break deploys. Route additions/removals/handler changes still fail.
 */

$root = dirname(__DIR__, 2);
$generator = $root . '/scripts/test/route_inventory.php';
$snapshot = $root . '/scripts/test/fixtures/route_inventory.json';

foreach ([$generator, $snapshot] as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing route inventory dependency: {$path}\n");
        exit(1);
    }
}

$actualJson = shell_exec('php ' . escapeshellarg($generator));
if ($actualJson === null || trim($actualJson) === '') {
    fwrite(STDERR, "Route inventory generator produced no output.\n");
    exit(1);
}

$actual = json_decode($actualJson, true);
$expected = json_decode((string) file_get_contents($snapshot), true);

if (!is_array($actual)) {
    fwrite(STDERR, "Generated route inventory is not valid JSON.\n");
    exit(1);
}

if (!is_array($expected)) {
    fwrite(STDERR, "Committed route inventory snapshot is not valid JSON.\n");
    exit(1);
}

$actualNormalized = normalizeRouteInventory($actual);
$expectedNormalized = normalizeRouteInventory($expected);

if ($actualNormalized !== $expectedNormalized) {
    fwrite(STDERR, "Route inventory differs from the committed snapshot.\n");

    $actualKeys = array_keys($actualNormalized);
    $expectedKeys = array_keys($expectedNormalized);

    $added = array_values(array_diff($actualKeys, $expectedKeys));
    $removed = array_values(array_diff($expectedKeys, $actualKeys));
    $changed = [];

    foreach (array_intersect($actualKeys, $expectedKeys) as $key) {
        if ($actualNormalized[$key] !== $expectedNormalized[$key]) {
            $changed[] = $key;
        }
    }

    if ($added !== []) {
        fwrite(STDERR, "Added routes:\n");
        foreach (array_slice($added, 0, 40) as $key) {
            fwrite(STDERR, "  + {$key}\n");
        }
    }

    if ($removed !== []) {
        fwrite(STDERR, "Removed routes:\n");
        foreach (array_slice($removed, 0, 40) as $key) {
            fwrite(STDERR, "  - {$key}\n");
        }
    }

    if ($changed !== []) {
        fwrite(STDERR, "Changed routes:\n");
        foreach (array_slice($changed, 0, 40) as $key) {
            fwrite(STDERR, "  * {$key}\n");
        }
    }

    if ($added === [] && $removed === [] && $changed === []) {
        fwrite(STDERR, "No semantic route differences found; check inventory normalization.\n");
    }

    exit(1);
}

echo "Route inventory static checks passed.\n";

/**
 * @param array<int,array<string,mixed>> $routes
 * @return array<string,array<string,mixed>>
 */
function normalizeRouteInventory(array $routes): array
{
    $normalized = [];

    foreach ($routes as $route) {
        if (!is_array($route)) {
            continue;
        }

        ksort($route);

        $scope = (string) ($route['scope'] ?? '');
        $method = (string) ($route['method'] ?? '');
        $path = (string) ($route['path'] ?? '');
        $handler = (string) ($route['handler'] ?? '');

        $key = $scope . ' ' . $method . ' ' . $path . ' ' . $handler;
        $normalized[$key] = $route;
    }

    ksort($normalized);

    return $normalized;
}

// End of file.
