<?php

declare(strict_types=1);

/**
 * Static coverage for collapsed all-status tenant admin site-image pickers.
 *
 * This inspects the siteImagePicker() helper body, not the whole controller,
 * because unrelated controller code may legitimately compare artwork status.
 */

$root = dirname(__DIR__, 2);
$settingsPath = $root . '/app/Http/Controllers/Tenant/Admin/SettingsController.php';
$contentPath = $root . '/app/Http/Controllers/Tenant/Admin/ContentController.php';
$cssPath = $root . '/public/assets/tenant-admin.css';

foreach ([$settingsPath, $contentPath, $cssPath] as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing required file: {$path}\n");
        exit(1);
    }
}

$settings = file_get_contents($settingsPath);
$content = file_get_contents($contentPath);
$css = file_get_contents($cssPath);

foreach ([$settingsPath => $settings, $contentPath => $content] as $path => $source) {
    $pickerBody = methodBody($source, 'siteImagePicker');

    $required = [
        'site-image-picker-shell',
        'site-image-picker-summary',
        '$summaryClass =',
        'Selected image',
        '<details class="site-image-picker-shell">',
        'atype.code = \'site_images\'',
        'COALESCE(a.status, \'\') <> \'archived\'',
        '$status = (string) ($row[\'status\'] ?? \'draft\');',
        '$isPublished = $status === \'published\';',
        '\'src\' => $isPublished ? \'/admin/media?uuid=\' . rawurlencode($uuid) . \'&variant=thumb\' : \'\'',
        'site-image-picker-draft-warning',
        'draft: will not show in interface until published.',
        '$cardClass =',
    ];

    foreach ($required as $needle) {
        if (!str_contains($pickerBody, $needle)) {
            fwrite(STDERR, "Missing site-image picker marker in {$path}: {$needle}\n");
            exit(1);
        }
    }

    $forbidden = [
        'a.status = \'published\'',
        'a.status IN (\'published\')',
        'a.status <> \'draft\'',
        '\'src\' => \'/admin/media?uuid=\' . rawurlencode($uuid) . \'&variant=thumb\'',
        '\'src\' => \'/media?uuid=\' . rawurlencode($uuid) . \'&variant=thumb\'',
    ];

    foreach ($forbidden as $needle) {
        if (str_contains($pickerBody, $needle)) {
            fwrite(STDERR, "Forbidden picker-only published/public thumbnail behavior remains in {$path}: {$needle}\n");
            exit(1);
        }
    }
}

$cssRequired = [
    '.site-image-picker-shell',
    '.site-image-picker-summary',
    '.site-image-picker-card img',
    '.site-image-picker-draft-warning',
    '.site-image-picker-card.is-draft',
    '.site-image-picker-summary.is-draft',
];

foreach ($cssRequired as $needle) {
    if (!str_contains($css, $needle)) {
        fwrite(STDERR, "Missing picker CSS marker: {$needle}\n");
        exit(1);
    }
}

echo "Collapsed all-status Site Images picker static checks passed.\n";

/**
 * Extract a method body for targeted static checks.
 */
function methodBody(string $source, string $name): string
{
    if (!preg_match('/^\s*(?:private|protected|public)\s+function\s+' . preg_quote($name, '/') . '\s*\(/m', $source, $match, PREG_OFFSET_CAPTURE)) {
        fwrite(STDERR, "Missing method: {$name}\n");
        exit(1);
    }

    $start = $match[0][1];
    $brace = strpos($source, '{', $start);
    if ($brace === false) {
        fwrite(STDERR, "Missing opening brace for method: {$name}\n");
        exit(1);
    }

    $depth = 0;
    $inSingle = false;
    $inDouble = false;
    $escaped = false;
    $len = strlen($source);

    for ($i = $brace; $i < $len; $i++) {
        $char = $source[$i];

        if ($escaped) {
            $escaped = false;
            continue;
        }
        if ($char === '\\') {
            $escaped = true;
            continue;
        }
        if ($inSingle) {
            if ($char === "'") {
                $inSingle = false;
            }
            continue;
        }
        if ($inDouble) {
            if ($char === '"') {
                $inDouble = false;
            }
            continue;
        }
        if ($char === "'") {
            $inSingle = true;
            continue;
        }
        if ($char === '"') {
            $inDouble = true;
            continue;
        }

        if ($char === '{') {
            $depth++;
        } elseif ($char === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($source, $start, $i - $start + 1);
            }
        }
    }

    fwrite(STDERR, "Missing closing brace for method: {$name}\n");
    exit(1);
}

// End of file.
