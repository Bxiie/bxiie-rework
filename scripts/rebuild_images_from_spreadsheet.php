<?php
/**
 * Rebuild Bxiie CMS image records from a spreadsheet-export CSV.
 *
 * This script is destructive by design:
 * - Deletes all existing image rows for the selected tenant.
 * - Deletes image/section joins automatically through ON DELETE CASCADE.
 * - Optionally deletes existing portfolio sections for the tenant.
 * - Optionally deletes existing imported image storage/cache.
 *
 * CSV rules:
 * - "name" becomes images.title and images.alt_text.
 * - "File Name" is the source file to import.
 * - "medium" becomes images.medium.
 * - "year" becomes images.year.
 * - Columns after "year" are treated as assignment columns.
 * - If a cell contains "x", the image is assigned to that column's section.
 * - Special columns:
 *   - "rotator" sets featured_rotator = 1.
 *   - "home" sets featured_home = 1 and also creates/assigns the Home section.
 *   - "all" assigns the image to the "All Images" section.
 *
 * Watermark filename rule:
 * - If the spreadsheet says "test.jpg", the importer first looks for "test.jpg".
 * - If not found, it looks for "test_wm.jpg".
 * - If the matched file has "_wm" before the extension, images.watermarked is set to 1.
 * - If the spreadsheet already says "test_wm.jpg", that file is imported directly.
 *
 * Expected command:
 *
 *   sudo -u www-data \
 *     DATABASE_PATH='/var/lib/bxiie-cms/database/bxiie.sqlite' \
 *     STORAGE_PATH='/var/lib/bxiie-cms/storage' \
 *     php scripts/rebuild_images_from_spreadsheet.php \
 *       --csv=/var/tmp/site_images.csv \
 *       --source=/var/www/bxiie \
 *       --tenant=bxiie \
 *       --delete-storage \
 *       --delete-sections
 */

declare(strict_types=1);

const REQUIRED_HEADERS = ['name', 'file name', 'medium', 'year'];
const SUPPORTED_MIME_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

function usage(): void
{
    fwrite(STDERR, <<<TEXT
Usage:
  php scripts/rebuild_images_from_spreadsheet.php --csv=/path/site_images.csv --source=/path/old/images [options]

Required:
  --csv=/path/site_images.csv      CSV exported from the spreadsheet.
  --source=/path/old/images        Directory tree containing the named image files.

Options:
  --tenant=bxiie                   Tenant slug. Default: bxiie.
  --delete-storage                 Delete imported tenant originals/cache before import.
  --delete-sections                Delete existing tenant portfolio sections before import.
  --dry-run                        Validate and print actions without changing files or DB.
  --help                           Show this help.

Destructive:
  Existing image rows for the tenant are deleted before import.

TEXT);
}

function fail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function logLine(string $message): void
{
    fwrite(STDOUT, "[spreadsheet-import] {$message}\n");
}

function hasFlag(array $options, string $name): bool
{
    return array_key_exists($name, $options);
}

function optionValue(array $options, string $name, ?string $default = null): ?string
{
    return isset($options[$name]) && is_string($options[$name]) ? $options[$name] : $default;
}

function normalizeHeader(string $header): string
{
    $header = trim($header);
    $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
    $header = preg_replace('/\s+/', ' ', $header) ?? $header;

    return strtolower($header);
}

function displayHeader(string $header): string
{
    $header = trim($header);
    $header = preg_replace('/\s+/', ' ', $header) ?? $header;

    return $header;
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value !== '' ? $value : 'untitled';
}

function isMarked(mixed $value): bool
{
    $value = strtolower(trim((string) $value));

    return in_array($value, ['x', 'yes', 'y', 'true', '1'], true);
}

function removeDirectoryContents(string $path, bool $dryRun): void
{
    if (!is_dir($path)) {
        return;
    }

    if ($dryRun) {
        logLine("Would delete contents of {$path}");
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if (!$item instanceof SplFileInfo) {
            continue;
        }

        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
}

function ensureDirectory(string $path, bool $dryRun): void
{
    if (is_dir($path)) {
        return;
    }

    if ($dryRun) {
        logLine("Would create directory {$path}");
        return;
    }

    if (!mkdir($path, 0775, true) && !is_dir($path)) {
        fail("Unable to create directory: {$path}");
    }

    chmod($path, 0775);
}

function loadPdo(string $databasePath): PDO
{
    if (!is_file($databasePath)) {
        fail("Database file does not exist: {$databasePath}");
    }

    $pdo = new PDO('sqlite:' . $databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');

    return $pdo;
}

function getTenantId(PDO $pdo, string $tenantSlug): int
{
    $stmt = $pdo->prepare('SELECT id FROM tenants WHERE slug = :slug');
    $stmt->execute(['slug' => $tenantSlug]);
    $row = $stmt->fetch();

    if (!$row) {
        fail("Tenant not found: {$tenantSlug}");
    }

    return (int) $row['id'];
}

function readCsv(string $csvPath): array
{
    if (!is_file($csvPath)) {
        fail("CSV file not found: {$csvPath}");
    }

    $handle = fopen($csvPath, 'rb');
    if (!$handle) {
        fail("Unable to open CSV: {$csvPath}");
    }

    $rawHeaders = fgetcsv($handle);
    if ($rawHeaders === false) {
        fail("CSV is empty: {$csvPath}");
    }

    $headers = [];
    $displayHeaders = [];
    foreach ($rawHeaders as $index => $header) {
        $normalized = normalizeHeader((string) $header);
        $headers[$index] = $normalized;
        $displayHeaders[$index] = displayHeader((string) $header);
    }

    foreach (REQUIRED_HEADERS as $required) {
        if (!in_array($required, $headers, true)) {
            fail("CSV missing required header: {$required}");
        }
    }

    $rows = [];
    while (($values = fgetcsv($handle)) !== false) {
        $record = [];
        $isBlank = true;

        foreach ($headers as $index => $header) {
            $value = $values[$index] ?? '';
            if (trim((string) $value) !== '') {
                $isBlank = false;
            }

            $record[$header] = $value;
            $record['_display_headers'][$header] = $displayHeaders[$index] ?? $header;
        }

        if (!$isBlank) {
            $rows[] = $record;
        }
    }

    fclose($handle);

    return [$headers, $displayHeaders, $rows];
}

function findSourceFile(string $sourceRoot, string $requestedName): ?array
{
    $requestedName = trim($requestedName);
    if ($requestedName === '') {
        return null;
    }

    $candidateNames = [$requestedName];

    $pathInfo = pathinfo($requestedName);
    $dirname = $pathInfo['dirname'] ?? '.';
    $filename = $pathInfo['filename'] ?? $requestedName;
    $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';

    if (!str_ends_with($filename, '_wm')) {
        $wmName = $filename . '_wm' . $extension;
        $candidateNames[] = ($dirname !== '.' ? $dirname . DIRECTORY_SEPARATOR : '') . $wmName;
    }

    foreach ($candidateNames as $candidateName) {
        $directPath = rtrim($sourceRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $candidateName;
        if (is_file($directPath)) {
            return [
                'path' => realpath($directPath) ?: $directPath,
                'matched_name' => basename($candidateName),
                'watermarked' => preg_match('/_wm\.[^.]+$/i', basename($candidateName)) === 1,
            ];
        }
    }

    $baseCandidates = array_map('strtolower', array_map('basename', $candidateNames));
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $item) {
        if (!$item instanceof SplFileInfo || !$item->isFile()) {
            continue;
        }

        if (in_array(strtolower($item->getBasename()), $baseCandidates, true)) {
            return [
                'path' => $item->getRealPath() ?: $item->getPathname(),
                'matched_name' => $item->getBasename(),
                'watermarked' => preg_match('/_wm\.[^.]+$/i', $item->getBasename()) === 1,
            ];
        }
    }

    return null;
}

function getOrCreateSection(PDO $pdo, int $tenantId, string $sectionName, int $sortOrder, bool $dryRun): int
{
    $sectionName = trim($sectionName);
    if ($sectionName === '') {
        return 0;
    }

    $slug = slugify($sectionName);

    $stmt = $pdo->prepare('SELECT id FROM portfolio_sections WHERE tenant_id = :tenant_id AND slug = :slug');
    $stmt->execute([
        'tenant_id' => $tenantId,
        'slug' => $slug,
    ]);
    $row = $stmt->fetch();

    if ($row) {
        return (int) $row['id'];
    }

    if ($dryRun) {
        logLine("Would create section {$sectionName}");
        return 0;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO portfolio_sections (tenant_id, name, slug, description, sort_order)
         VALUES (:tenant_id, :name, :slug, NULL, :sort_order)'
    );
    $stmt->execute([
        'tenant_id' => $tenantId,
        'name' => $sectionName,
        'slug' => $slug,
        'sort_order' => $sortOrder,
    ]);

    return (int) $pdo->lastInsertId();
}

function linkImageToSection(PDO $pdo, int $imageId, int $sectionId, bool $dryRun): void
{
    if ($imageId <= 0 || $sectionId <= 0) {
        return;
    }

    if ($dryRun) {
        logLine("Would link image {$imageId} to section {$sectionId}");
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT OR IGNORE INTO image_sections (image_id, section_id)
         VALUES (:image_id, :section_id)'
    );
    $stmt->execute([
        'image_id' => $imageId,
        'section_id' => $sectionId,
    ]);
}

function makeDerivative(string $source, string $target, int $maxWidth, bool $watermark, string $watermarkText): void
{
    [$width, $height] = getimagesize($source) ?: [0, 0];

    if ($width <= 0 || $height <= 0) {
        fail("Unable to read dimensions for {$source}");
    }

    $blob = file_get_contents($source);
    if ($blob === false) {
        fail("Unable to read image file {$source}");
    }

    $sourceImage = imagecreatefromstring($blob);
    if (!$sourceImage) {
        fail("GD could not decode {$source}");
    }

    $ratio = min(1, $maxWidth / max(1, $width));
    $targetWidth = max(1, (int) round($width * $ratio));
    $targetHeight = max(1, (int) round($height * $ratio));

    $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
    imagecopyresampled(
        $targetImage,
        $sourceImage,
        0,
        0,
        0,
        0,
        $targetWidth,
        $targetHeight,
        $width,
        $height
    );

    if ($watermark) {
        $color = imagecolorallocatealpha($targetImage, 255, 255, 255, 55);
        imagestring(
            $targetImage,
            5,
            max(12, $targetWidth - 12 - strlen($watermarkText) * 10),
            max(12, $targetHeight - 32),
            $watermarkText,
            $color
        );
    }

    $targetDir = dirname($target);
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        fail("Unable to create derivative directory {$targetDir}");
    }

    imagejpeg($targetImage, $target, 86);
    chmod($target, 0664);

    imagedestroy($sourceImage);
    imagedestroy($targetImage);
}

function insertImage(
    PDO $pdo,
    int $tenantId,
    string $title,
    string $medium,
    string $year,
    string $storageKey,
    string $originalPath,
    string $mimeType,
    int $width,
    int $height,
    bool $featuredHome,
    bool $featuredRotator,
    bool $watermarked,
    int $sortOrder,
    bool $dryRun
): int {
    if ($dryRun) {
        logLine("Would insert image {$title}");
        return 0;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO images (
            tenant_id,
            title,
            description,
            medium,
            year,
            alt_text,
            sort_order,
            storage_key,
            original_path,
            mime_type,
            width,
            height,
            is_public,
            is_draft,
            featured_home,
            featured_rotator,
            watermarked,
            created_at
        ) VALUES (
            :tenant_id,
            :title,
            NULL,
            :medium,
            :year,
            :alt_text,
            :sort_order,
            :storage_key,
            :original_path,
            :mime_type,
            :width,
            :height,
            1,
            0,
            :featured_home,
            :featured_rotator,
            :watermarked,
            datetime("now")
        )'
    );

    $stmt->execute([
        'tenant_id' => $tenantId,
        'title' => $title,
        'medium' => $medium !== '' ? $medium : null,
        'year' => $year !== '' ? $year : null,
        'alt_text' => $title,
        'sort_order' => $sortOrder,
        'storage_key' => $storageKey,
        'original_path' => $originalPath,
        'mime_type' => $mimeType,
        'width' => $width,
        'height' => $height,
        'featured_home' => $featuredHome ? 1 : 0,
        'featured_rotator' => $featuredRotator ? 1 : 0,
        'watermarked' => $watermarked ? 1 : 0,
    ]);

    return (int) $pdo->lastInsertId();
}

$options = getopt('', [
    'csv:',
    'source:',
    'tenant::',
    'delete-storage',
    'delete-sections',
    'dry-run',
    'help',
]);

if ($options === false || hasFlag($options, 'help')) {
    usage();
    exit($options === false ? 1 : 0);
}

$csvPath = optionValue($options, 'csv');
$sourceRoot = optionValue($options, 'source');
$tenantSlug = optionValue($options, 'tenant', 'bxiie') ?? 'bxiie';
$deleteStorage = hasFlag($options, 'delete-storage');
$deleteSections = hasFlag($options, 'delete-sections');
$dryRun = hasFlag($options, 'dry-run');

if ($csvPath === null || $sourceRoot === null) {
    usage();
    fail('Missing --csv or --source.');
}

if (!is_dir($sourceRoot)) {
    fail("Source directory does not exist: {$sourceRoot}");
}

if (!extension_loaded('gd')) {
    fail('PHP GD extension is required.');
}

if (!extension_loaded('pdo_sqlite')) {
    fail('PHP pdo_sqlite extension is required.');
}

$databasePath = getenv('DATABASE_PATH') ?: __DIR__ . '/../database/bxiie.sqlite';
$storagePath = rtrim(getenv('STORAGE_PATH') ?: __DIR__ . '/../storage', DIRECTORY_SEPARATOR);
$watermarkText = getenv('WATERMARK_TEXT') ?: '© Bxiie';

[$headers, $displayHeaders, $rows] = readCsv($csvPath);
$pdo = loadPdo($databasePath);
$tenantId = getTenantId($pdo, $tenantSlug);

$uploadBase = "{$storagePath}/uploads/{$tenantSlug}/originals";
$cacheBase = "{$storagePath}/cache/{$tenantId}";

logLine("CSV: {$csvPath}");
logLine("Source: {$sourceRoot}");
logLine("Tenant: {$tenantSlug} #{$tenantId}");
logLine("Rows: " . count($rows));
logLine($dryRun ? 'Dry run: yes' : 'Dry run: no');

$baseColumns = ['name', 'file name', 'medium', 'year'];
$assignmentHeaders = [];
$afterYear = false;

foreach ($headers as $index => $normalizedHeader) {
    if ($normalizedHeader === 'year') {
        $afterYear = true;
        continue;
    }

    if (!$afterYear || $normalizedHeader === '') {
        continue;
    }

    $assignmentHeaders[$normalizedHeader] = $displayHeaders[$index] ?? $normalizedHeader;
}

if (empty($assignmentHeaders)) {
    fail('No section/assignment columns found after year.');
}

$pdo->beginTransaction();

try {
    if (!$dryRun) {
        logLine('Deleting existing image rows for tenant.');
        $stmt = $pdo->prepare('DELETE FROM images WHERE tenant_id = :tenant_id');
        $stmt->execute(['tenant_id' => $tenantId]);

        if ($deleteSections) {
            logLine('Deleting existing portfolio sections for tenant.');
            $stmt = $pdo->prepare('DELETE FROM portfolio_sections WHERE tenant_id = :tenant_id');
            $stmt->execute(['tenant_id' => $tenantId]);
        }
    } else {
        logLine('Would delete existing image rows for tenant.');
        if ($deleteSections) {
            logLine('Would delete existing portfolio sections for tenant.');
        }
    }

    if ($deleteStorage) {
        removeDirectoryContents($uploadBase, $dryRun);
        removeDirectoryContents($cacheBase, $dryRun);
    }

    ensureDirectory($uploadBase, $dryRun);
    ensureDirectory($cacheBase, $dryRun);

    $imported = 0;
    $missing = 0;
    $skipped = 0;
    $sortOrder = 100;

    foreach ($rows as $rowNumber => $row) {
        $title = trim((string) ($row['name'] ?? ''));
        $fileName = trim((string) ($row['file name'] ?? ''));
        $medium = trim((string) ($row['medium'] ?? ''));
        $year = trim((string) ($row['year'] ?? ''));

        if ($title === '' && $fileName === '') {
            ++$skipped;
            continue;
        }

        if ($fileName === '') {
            ++$missing;
            logLine('Missing file name for title: ' . ($title !== '' ? $title : '[untitled]'));
            continue;
        }

        $match = findSourceFile($sourceRoot, $fileName);
        if ($match === null) {
            ++$missing;
            logLine("Missing source file for spreadsheet file name: {$fileName}");
            continue;
        }

        $sourcePath = $match['path'];
        $mimeType = mime_content_type($sourcePath);
        if (!isset(SUPPORTED_MIME_TYPES[$mimeType])) {
            ++$skipped;
            logLine("Skipping unsupported MIME {$mimeType}: {$sourcePath}");
            continue;
        }

        [$width, $height] = getimagesize($sourcePath) ?: [0, 0];
        if ($width <= 0 || $height <= 0) {
            ++$skipped;
            logLine("Skipping unreadable image dimensions: {$sourcePath}");
            continue;
        }

        $storageKey = bin2hex(random_bytes(12));
        $extension = SUPPORTED_MIME_TYPES[$mimeType];
        $safeBaseName = slugify(pathinfo($match['matched_name'], PATHINFO_FILENAME));
        $targetFileName = sprintf('%04d-%s.%s', $sortOrder, $safeBaseName, $extension);
        $targetOriginal = "{$uploadBase}/{$targetFileName}";
        $originalPath = $targetOriginal;
        $title = $title !== '' ? $title : pathinfo($fileName, PATHINFO_FILENAME);
        $watermarked = (bool) $match['watermarked'];

        if ($dryRun) {
            logLine("Would import {$fileName} from {$sourcePath}");
        } else {
            if (!copy($sourcePath, $targetOriginal)) {
                throw new RuntimeException("Failed copying {$sourcePath} to {$targetOriginal}");
            }

            chmod($targetOriginal, 0664);

            foreach (['thumb' => 420, 'medium' => 1200, 'large' => 2200] as $label => $maxWidth) {
                makeDerivative(
                    $targetOriginal,
                    "{$cacheBase}/{$storageKey}-{$label}.jpg",
                    $maxWidth,
                    false,
                    $watermarkText
                );
            }
        }

        $featuredHome = isMarked($row['home'] ?? '');
        $featuredRotator = isMarked($row['rotator'] ?? '');

        $imageId = insertImage(
            $pdo,
            $tenantId,
            $title,
            $medium,
            $year,
            $storageKey,
            $originalPath,
            $mimeType,
            $width,
            $height,
            $featuredHome,
            $featuredRotator,
            $watermarked,
            $sortOrder,
            $dryRun
        );

        $sectionSortOrder = 100;
        foreach ($assignmentHeaders as $normalizedHeader => $displayName) {
            if (!isMarked($row[$normalizedHeader] ?? '')) {
                $sectionSortOrder += 10;
                continue;
            }

            if ($normalizedHeader === 'rotator') {
                $sectionSortOrder += 10;
                continue;
            }

            $sectionName = $displayName;
            if ($normalizedHeader === 'all') {
                $sectionName = 'All Images';
            }

            $sectionId = getOrCreateSection($pdo, $tenantId, $sectionName, $sectionSortOrder, $dryRun);
            linkImageToSection($pdo, $imageId, $sectionId, $dryRun);
            $sectionSortOrder += 10;
        }

        ++$imported;
        $sortOrder += 10;
        logLine("Imported {$fileName} as {$title}");
    }

    if ($dryRun) {
        $pdo->rollBack();
    } else {
        $pdo->commit();
    }

    logLine("Complete. imported={$imported}, missing={$missing}, skipped={$skipped}");
} catch (Throwable $exception) {
    $pdo->rollBack();
    fail($exception->getMessage());
}

// End of file.
