<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = glob($root . '/database/migrations/*.sql') ?: [];
$errors = [];
$prefixes = [];

foreach ($files as $file) {
    $name = basename($file);
    if (preg_match('/^(\d{4})_[a-z0-9_]+\.sql$/', $name, $matches) !== 1) {
        $errors[] = "Invalid migration filename: {$name}";
        continue;
    }
    $prefix = (int) $matches[1];
    $prefixes[$prefix][] = $name;
}

foreach ($prefixes as $prefix => $names) {
    if ($prefix >= 38 && count($names) > 1) {
        $errors[] = sprintf('Duplicate migration prefix %04d: %s', $prefix, implode(', ', $names));
    }
}

$modern = array_keys(array_filter($prefixes, static fn (array $names, int $prefix): bool => $prefix >= 38, ARRAY_FILTER_USE_BOTH));
sort($modern);
if ($modern !== []) {
    $expected = range(38, max($modern));
    $missing = array_values(array_diff($expected, $modern));
    if ($missing !== []) {
        $errors[] = 'Missing modern migration prefixes: ' . implode(', ', array_map(static fn (int $value): string => sprintf('%04d', $value), $missing));
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

echo "Migration numbering static checks passed.\n";
