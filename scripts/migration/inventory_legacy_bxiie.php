<?php

declare(strict_types=1);

/**
 * Inventories a mirrored legacy Bxiie site directory.
 */

$options = getopt('', ['source:', 'output::']);
$source = rtrim((string) ($options['source'] ?? ''), '/');
$output = (string) ($options['output'] ?? 'storage/imports/bxiie-legacy-inventory.json');

if ($source === '' || !is_dir($source)) {
    fwrite(STDERR, "Required: --source=/path/to/legacy/site\n");
    exit(1);
}

$root = dirname(__DIR__, 2);
$outputPath = $root . '/' . ltrim($output, '/');
if (!is_dir(dirname($outputPath))) {
    mkdir(dirname($outputPath), 0775, true);
}

$inventory = [
    'source' => $source,
    'generated_at' => date('c'),
    'images' => [],
    'pages' => [],
    'other' => [],
];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));

foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile()) {
        continue;
    }

    $path = $file->getPathname();
    $relative = ltrim(substr($path, strlen($source)), '/');
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $record = [
        'relative_path' => $relative,
        'absolute_path' => $path,
        'size_bytes' => $file->getSize(),
        'modified_at' => date('c', $file->getMTime()),
    ];

    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true)) {
        $dimensions = @getimagesize($path);
        if (is_array($dimensions)) {
            $record['width'] = $dimensions[0] ?? null;
            $record['height'] = $dimensions[1] ?? null;
            $record['mime_type'] = $dimensions['mime'] ?? null;
        }
        $inventory['images'][] = $record;
        continue;
    }

    if (in_array($extension, ['html', 'htm', 'php', 'txt', 'md'], true)) {
        $contents = file_get_contents($path);
        $record['excerpt'] = is_string($contents) ? trim(substr(preg_replace('/\s+/', ' ', strip_tags($contents)), 0, 500)) : null;
        $inventory['pages'][] = $record;
        continue;
    }

    $inventory['other'][] = $record;
}

$inventory['counts'] = [
    'images' => count($inventory['images']),
    'pages' => count($inventory['pages']),
    'other' => count($inventory['other']),
];

file_put_contents($outputPath, json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

echo json_encode(['ok' => true, 'output' => $outputPath, 'counts' => $inventory['counts']], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
