<?php
/**
 * Import images from an old static Bxiie site into the Bxiie CMS.
 *
 * This script is intentionally idempotent:
 * - Existing images are detected by tenant_id + original_path.
 * - Existing portfolio sections are reused by slug.
 * - Existing image/section links are not duplicated.
 *
 * Run from the application root, for example:
 *
 *   sudo -u www-data \
 *     DATABASE_PATH='/var/lib/bxiie-cms/database/bxiie.sqlite' \
 *     STORAGE_PATH='/var/lib/bxiie-cms/storage' \
 *     php scripts/import_static_images.php \
 *       --source=/var/www/bxiie-static-backup \
 *       --tenant=bxiie \
 *       --section-from-dir \
 *       --featured-home-count=12 \
 *       --featured-rotator-count=20
 */

declare(strict_types=1);

const SUPPORTED_MIME_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

const DEFAULT_SECTION_NAME = 'Imported';

function usage(): void
{
    $message = <<<TEXT
Usage:
  php scripts/import_static_images.php --source=/path/to/old/site [options]

Required:
  --source=/path          Old static site directory or image directory.

Options:
  --tenant=bxiie          Tenant slug to import into. Default: bxiie.
  --section=Portfolio     Section name for all imports. Default: Imported.
  --section-from-dir      Use each image parent directory as the portfolio section.
  --dry-run               Show what would happen without copying or writing DB rows.
  --watermarked           Mark imported image records as watermarked.
  --featured-home-count=N Mark first N imported images as featured_home.
  --featured-rotator-count=N
                          Mark first N imported images as featured_rotator.
  --limit=N               Stop after N supported images. Useful for testing.
  --help                  Show this help.

Examples:
  sudo -u www-data \\
    DATABASE_PATH='/var/lib/bxiie-cms/database/bxiie.sqlite' \\
    STORAGE_PATH='/var/lib/bxiie-cms/storage' \\
    php scripts/import_static_images.php \\
      --source=/var/www/bxiie-static-backup \\
      --tenant=bxiie \\
      --section-from-dir \\
      --featured-home-count=12 \\
      --featured-rotator-count=20

TEXT;

    fwrite(STDERR, $message);
}

function optionValue(array $options, string $name, ?string $default = null): ?string
{
    return isset($options[$name]) && is_string($options[$name]) ? $options[$name] : $default;
}

function hasFlag(array $options, string $name): bool
{
    return array_key_exists($name, $options);
}

function fail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function logLine(string $message): void
{
    fwrite(STDOUT, "[import] {$message}\n");
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value !== '' ? $value : 'imported';
}

function titleFromFilename(string $filename): string
{
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $name = preg_replace('/[_-]+/', ' ', $name) ?? $name;
    $name = preg_replace('/\s+/', ' ', $name) ?? $name;
    $name = trim($name);

    return $name !== '' ? ucwords($name) : 'Untitled';
}

function safeRelativePath(string $relativePath): string
{
    $parts = preg_split('#[\\/]+#', $relativePath) ?: [];
    $safeParts = [];

    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '' || $part === '.' || $part === '..') {
            continue;
        }

        $info = pathinfo($part);
        $base = slugify($info['filename'] ?? $part);
        $extension = isset($info['extension']) ? strtolower(preg_replace('/[^a-z0-9]/', '', $info['extension']) ?? '') : '';

        if ($extension !== '') {
            $safeParts[] = "{$base}.{$extension}";
        } else {
            $safeParts[] = $base;
        }
    }

    return implode('/', $safeParts);
}

function relativePath(string $base, string $path): string
{
    $base = rtrim(realpath($base) ?: $base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $path = realpath($path) ?: $path;

    if (str_starts_with($path, $base)) {
        return substr($path, strlen($base));
    }

    return basename($path);
}

function ensureDirectory(string $path, bool $dryRun): void
{
    if (is_dir($path)) {
        return;
    }

    if ($dryRun) {
        logLine("Would create directory: {$path}");
        return;
    }

    if (!mkdir($path, 0775, true) && !is_dir($path)) {
        fail("Unable to create directory: {$path}");
    }
}

function loadPdo(string $databasePath): PDO
{
    $dir = dirname($databasePath);
    if (!is_dir($dir)) {
        fail("Database directory does not exist: {$dir}");
    }

    $pdo = new PDO('sqlite:' . $databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');

    return $pdo;
}

function tenantId(PDO $pdo, string $tenantSlug): int
{
    $stmt = $pdo->prepare('SELECT id FROM tenants WHERE slug = :slug');
    $stmt->execute(['slug' => $tenantSlug]);
    $row = $stmt->fetch();

    if (!$row) {
        fail("Tenant not found: {$tenantSlug}");
    }

    return (int) $row['id'];
}

function findImages(string $source): array
{
    if (!is_dir($source)) {
        fail("Source directory does not exist: {$source}");
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
            continue;
        }

        $path = $fileInfo->getPathname();
        $basename = $fileInfo->getBasename();

        if (str_starts_with($basename, '.')) {
            continue;
        }

        $extension = strtolower($fileInfo->getExtension());
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            continue;
        }

        $mime = mime_content_type($path);
        if (!isset(SUPPORTED_MIME_TYPES[$mime])) {
            logLine("Skipping unsupported MIME {$mime}: {$path}");
            continue;
        }

        $files[] = $path;
    }

    sort($files, SORT_NATURAL | SORT_FLAG_CASE);

    return $files;
}

function getOrCreateSection(PDO $pdo, int $tenantId, string $sectionName, bool $dryRun): int
{
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
        logLine("Would create section: {$sectionName} ({$slug})");
        return 0;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO portfolio_sections (tenant_id, name, slug, description, sort_order)
         VALUES (:tenant_id, :name, :slug, :description, :sort_order)'
    );
    $stmt->execute([
        'tenant_id' => $tenantId,
        'name' => $sectionName,
        'slug' => $slug,
        'description' => null,
        'sort_order' => 100,
    ]);

    return (int) $pdo->lastInsertId();
}

function existingImageId(PDO $pdo, int $tenantId, string $originalPath): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM images WHERE tenant_id = :tenant_id AND original_path = :original_path');
    $stmt->execute([
        'tenant_id' => $tenantId,
        'original_path' => $originalPath,
    ]);

    $row = $stmt->fetch();

    return $row ? (int) $row['id'] : null;
}

function linkImageToSection(PDO $pdo, int $imageId, int $sectionId, bool $dryRun): void
{
    if ($sectionId <= 0) {
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
        fail("Cannot read image dimensions: {$source}");
    }

    $ratio = min(1, $maxWidth / max(1, $width));
    $newWidth = max(1, (int) round($width * $ratio));
    $newHeight = max(1, (int) round($height * $ratio));

    $blob = file_get_contents($source);
    if ($blob === false) {
        fail("Cannot read source image: {$source}");
    }

    $src = imagecreatefromstring($blob);
    if (!$src) {
        fail("GD cannot decode image: {$source}");
    }

    $dst = imagecreatetruecolor($newWidth, $newHeight);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    if ($watermark) {
        $color = imagecolorallocatealpha($dst, 255, 255, 255, 55);
        imagestring(
            $dst,
            5,
            max(12, $newWidth - 12 - strlen($watermarkText) * 10),
            max(12, $newHeight - 32),
            $watermarkText,
            $color
        );
    }

    $targetDir = dirname($target);
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        fail("Unable to create cache directory: {$targetDir}");
    }

    imagejpeg($dst, $target, 86);
    imagedestroy($src);
    imagedestroy($dst);
}

function insertImage(
    PDO $pdo,
    int $tenantId,
    string $title,
    string $storageKey,
    string $originalPath,
    string $mimeType,
    int $width,
    int $height,
    bool $featuredHome,
    bool $featuredRotator,
    bool $watermarked,
    bool $dryRun
): int {
    if ($dryRun) {
        logLine("Would insert image: {$title} ({$originalPath})");
        return 0;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO images (
            tenant_id,
            title,
            description,
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
            :description,
            :alt_text,
            :sort_order,
            :storage_key,
            :original_path,
            :mime_type,
            :width,
            :height,
            :is_public,
            :is_draft,
            :featured_home,
            :featured_rotator,
            :watermarked,
            datetime("now")
        )'
    );

    $stmt->execute([
        'tenant_id' => $tenantId,
        'title' => $title,
        'description' => null,
        'alt_text' => $title,
        'sort_order' => 100,
        'storage_key' => $storageKey,
        'original_path' => $originalPath,
        'mime_type' => $mimeType,
        'width' => $width,
        'height' => $height,
        'is_public' => 1,
        'is_draft' => 0,
        'featured_home' => $featuredHome ? 1 : 0,
        'featured_rotator' => $featuredRotator ? 1 : 0,
        'watermarked' => $watermarked ? 1 : 0,
    ]);

    return (int) $pdo->lastInsertId();
}

$options = getopt('', [
    'source:',
    'tenant::',
    'section::',
    'section-from-dir',
    'dry-run',
    'watermarked',
    'featured-home-count::',
    'featured-rotator-count::',
    'limit::',
    'help',
]);

if ($options === false || hasFlag($options, 'help')) {
    usage();
    exit($options === false ? 1 : 0);
}

$source = optionValue($options, 'source');
if ($source === null) {
    usage();
    fail('Missing required --source option.');
}

$source = rtrim($source, DIRECTORY_SEPARATOR);
$tenantSlug = optionValue($options, 'tenant', 'bxiie') ?? 'bxiie';
$sectionName = optionValue($options, 'section', DEFAULT_SECTION_NAME) ?? DEFAULT_SECTION_NAME;
$sectionFromDir = hasFlag($options, 'section-from-dir');
$dryRun = hasFlag($options, 'dry-run');
$watermarked = hasFlag($options, 'watermarked');
$featuredHomeCount = (int) (optionValue($options, 'featured-home-count', '0') ?? '0');
$featuredRotatorCount = (int) (optionValue($options, 'featured-rotator-count', '0') ?? '0');
$limit = optionValue($options, 'limit');

if ($limit !== null && (!ctype_digit($limit) || (int) $limit < 1)) {
    fail('--limit must be a positive integer.');
}

$databasePath = getenv('DATABASE_PATH') ?: __DIR__ . '/../database/bxiie.sqlite';
$storagePath = rtrim(getenv('STORAGE_PATH') ?: __DIR__ . '/../storage', DIRECTORY_SEPARATOR);
$watermarkText = getenv('WATERMARK_TEXT') ?: '© Bxiie';

if (!extension_loaded('gd')) {
    fail('PHP GD extension is required.');
}

if (!extension_loaded('pdo_sqlite')) {
    fail('PHP pdo_sqlite extension is required.');
}

$pdo = loadPdo($databasePath);
$tenantId = tenantId($pdo, $tenantSlug);

$uploadBase = "{$storagePath}/uploads/{$tenantSlug}/originals";
$cacheBase = "{$storagePath}/cache/{$tenantId}";
ensureDirectory($uploadBase, $dryRun);
ensureDirectory($cacheBase, $dryRun);

$files = findImages($source);
if ($limit !== null) {
    $files = array_slice($files, 0, (int) $limit);
}

logLine('Source: ' . $source);
logLine('Tenant: ' . $tenantSlug . ' #' . $tenantId);
logLine('Database: ' . $databasePath);
logLine('Storage: ' . $storagePath);
logLine('Found supported images: ' . count($files));
logLine($dryRun ? 'Dry run: yes' : 'Dry run: no');

$imported = 0;
$skipped = 0;
$errors = 0;
$seen = 0;

foreach ($files as $sourcePath) {
    ++$seen;

    try {
        $relative = safeRelativePath(relativePath($source, $sourcePath));
        $mimeType = mime_content_type($sourcePath);

        if (!isset(SUPPORTED_MIME_TYPES[$mimeType])) {
            ++$skipped;
            logLine("Skipping unsupported file: {$sourcePath}");
            continue;
        }

        $storageKey = bin2hex(random_bytes(12));
        $extension = SUPPORTED_MIME_TYPES[$mimeType];
        $targetOriginal = "{$uploadBase}/{$relative}";
        $originalPath = $targetOriginal;

        $existingId = existingImageId($pdo, $tenantId, $originalPath);
        if ($existingId !== null) {
            ++$skipped;
            logLine("Already imported #{$existingId}: {$relative}");

            if ($sectionFromDir) {
                $sectionNameForFile = basename(dirname(relativePath($source, $sourcePath)));
                $sectionId = getOrCreateSection($pdo, $tenantId, $sectionNameForFile, $dryRun);
                linkImageToSection($pdo, $existingId, $sectionId, $dryRun);
            }

            continue;
        }

        [$width, $height] = getimagesize($sourcePath) ?: [0, 0];
        if ($width <= 0 || $height <= 0) {
            ++$errors;
            logLine("Unable to read dimensions: {$sourcePath}");
            continue;
        }

        $title = titleFromFilename(basename($sourcePath));

        if ($dryRun) {
            logLine("Would copy {$sourcePath} -> {$targetOriginal}");
        } else {
            ensureDirectory(dirname($targetOriginal), false);
            if (!copy($sourcePath, $targetOriginal)) {
                throw new RuntimeException("Failed copying to {$targetOriginal}");
            }

            chmod($targetOriginal, 0664);

            foreach (['thumb' => 420, 'medium' => 1200, 'large' => 2200] as $label => $maxWidth) {
                makeDerivative(
                    $targetOriginal,
                    "{$cacheBase}/{$storageKey}-{$label}.jpg",
                    $maxWidth,
                    $watermarked,
                    $watermarkText
                );
            }
        }

        $featuredHome = $featuredHomeCount > 0 && $imported < $featuredHomeCount;
        $featuredRotator = $featuredRotatorCount > 0 && $imported < $featuredRotatorCount;

        $imageId = insertImage(
            $pdo,
            $tenantId,
            $title,
            $storageKey,
            $originalPath,
            $mimeType,
            $width,
            $height,
            $featuredHome,
            $featuredRotator,
            $watermarked,
            $dryRun
        );

        if ($sectionFromDir) {
            $sectionNameForFile = basename(dirname(relativePath($source, $sourcePath)));
            $sectionId = getOrCreateSection($pdo, $tenantId, $sectionNameForFile, $dryRun);
            linkImageToSection($pdo, $imageId, $sectionId, $dryRun);
        } else {
            $sectionId = getOrCreateSection($pdo, $tenantId, $sectionName, $dryRun);
            linkImageToSection($pdo, $imageId, $sectionId, $dryRun);
        }

        ++$imported;
        logLine("Imported {$relative} as image #{$imageId}");
    } catch (Throwable $exception) {
        ++$errors;
        logLine("Failed {$sourcePath}: " . $exception->getMessage());
    }
}

logLine("Complete. Seen={$seen}, imported={$imported}, skipped={$skipped}, errors={$errors}");

if ($errors > 0) {
    exit(2);
}

// End of file.
