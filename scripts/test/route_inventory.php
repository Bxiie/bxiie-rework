<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = [
    'tenant' => $root . '/app/Http/Routes/tenant.php',
    'platform' => $root . '/app/Http/Routes/platform.php',
];

$inventory = [];
foreach ($files as $scope => $file) {
    $source = file_get_contents($file);
    if ($source === false) {
        throw new RuntimeException('Unable to read ' . $file);
    }
    preg_match_all('~\\$router->(get|post)\\(\\s*\'([^\']+)\'|\\$router->add\\(\\s*\'([^\']+)\'\\s*,\\s*\'([^\']+)\'~', $source, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        if (($match[1] ?? '') !== '') {
            $method = strtoupper((string) $match[1]);
            $path = (string) $match[2];
        } else {
            $method = strtoupper((string) ($match[3] ?? ''));
            $path = (string) ($match[4] ?? '');
        }
        $inventory[] = ['scope' => $scope, 'method' => $method, 'path' => $path];
    }
}

usort($inventory, static fn(array $a, array $b): int => [$a['scope'], $a['method'], $a['path']] <=> [$b['scope'], $b['method'], $b['path']]);
echo json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
