<?php

declare(strict_types=1);

/**
 * Stages legacy Bxiie images from an inventory file into ArtsFolio storage.
 */

$options = getopt('', ['inventory:', 'tenant:', 'output::']);
$root = dirname(__DIR__, 2);
$inventoryPath = $root . '/' . ltrim((string) ($options['inventory'] ?? ''), '/');
$tenant = strtolower(trim((string) ($options['tenant'] ?? '')));
$output = (string) ($options['output'] ?? "storage/imports/{$tenant}-legacy-image-manifest.json");

if ($tenant === '' || !is_file($inventoryPath)) {
    fwrite(STDERR, "Required: --tenant=bxiie --inventory=storage/imports/bxiie-legacy-inventory.json\n");
    exit(1);
}

$inventory = json_decode((string) file_get_contents($inventoryPath), true);
if (!is_array($inventory) || !isset($inventory['images']) || !is_array($inventory['images'])) {
    fwrite(STDERR, "Invalid inventory.\n");
    exit(1);
}

$targetDir = $root . "/storage/uploads/artwork/{$tenant}/legacy";
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0775, true);
}

$manifest = ['tenant' => $tenant, 'generated_at' => date('c'), 'images' => []];

foreach ($inventory['images'] as $image) {
    $source = (string) ($image['absolute_path'] ?? '');
    if ($source === '' || !is_file($source)) {
        continue;
    }

    $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
    $sha256 = hash_file('sha256', $source);
    $target = "{$targetDir}/{$sha256}.{$extension}";

    if (!is_file($target)) {
        copy($source, $target);
    }

    $manifest['images'][] = [
        'source' => $source,
        'legacy_relative_path' => $image['relative_path'] ?? null,
        'staged_path' => $target,
        'sha256' => $sha256,
        'suggested_title' => pathinfo((string) ($image['relative_path'] ?? basename($source)), PATHINFO_FILENAME),
        'width' => $image['width'] ?? null,
        'height' => $image['height'] ?? null,
        'mime_type' => $image['mime_type'] ?? null,
    ];
}

$outputPath = $root . '/' . ltrim($output, '/');
if (!is_dir(dirname($outputPath))) {
    mkdir(dirname($outputPath), 0775, true);
}

file_put_contents($outputPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

echo json_encode(['ok' => true, 'manifest' => $outputPath, 'staged_count' => count($manifest['images'])], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
