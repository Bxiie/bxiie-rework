<?php

declare(strict_types=1);

/**
 * Imports bxiie.com artwork from site_images.xlsx and legacy image inventory.
 *
 * This importer is idempotent by tenant + artwork slug. It updates matching
 * artwork records and media metadata, rather than blindly duplicating rows.
 *
 * Usage:
 *
 *   ARTSFOLIO_ENV_FILE=.env.local php scripts/migration/import_bxiie_site_images.php \
 *     --tenant=bxiie \
 *     --xlsx=storage/imports/site_images.xlsx \
 *     --audit=storage/imports/bxiie-spreadsheet-match-audit.json \
 *     --source=../bxiie
 */

use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;

require dirname(__DIR__, 2) . '/bootstrap/app.php';
#
$options = getopt('', [
    'tenant:',
    'host::',
    'xlsx:',
    'audit:',
    'source:',
    'dry-run::',
]);

$root = dirname(__DIR__, 2);
$tenantSlug = (string) ($options['tenant'] ?? 'bxiie');
$host = (string) ($options['host'] ?? 'bxiie.com');
$xlsxPath = $root . '/' . ltrim((string) ($options['xlsx'] ?? 'storage/imports/site_images.xlsx'), '/');
$auditPath = $root . '/' . ltrim((string) ($options['audit'] ?? 'storage/imports/bxiie-spreadsheet-match-audit.json'), '/');
$legacySource = rtrim((string) ($options['source'] ?? '../bxiie'), '/');
$dryRun = array_key_exists('dry-run', $options);

if (!str_starts_with($legacySource, '/')) {
    $legacySource = realpath($root . '/' . $legacySource) ?: $root . '/' . $legacySource;
}

if (!is_file($xlsxPath)) {
    fwrite(STDERR, "Missing XLSX: {$xlsxPath}\n");
    exit(1);
}

if (!is_file($auditPath)) {
    fwrite(STDERR, "Missing audit JSON. Run audit_bxiie_spreadsheet_matches.php first.\n");
    exit(1);
}

if (!is_dir($legacySource)) {
    fwrite(STDERR, "Missing legacy source directory: {$legacySource}\n");
    exit(1);
}

$pdo = Database::connect($root);
$tenant = (new TenantResolver($pdo))->resolveFromHost($host);

if (!$tenant || $tenant->slug !== $tenantSlug) {
    fwrite(STDERR, "Could not resolve tenant {$tenantSlug} from host {$host}.\n");
    exit(1);
}

$rows = readXlsxRows($xlsxPath);
$headers = array_map(static fn ($value): string => trim((string) $value), $rows[0]);

$columns = [
    'name' => array_search('name', $headers, true),
    'file' => array_search('File Name', $headers, true),
    'medium' => array_search('medium', $headers, true),
    'year' => array_search('year', $headers, true),
];

foreach ($columns as $name => $index) {
    if ($index === false) {
        fwrite(STDERR, "Missing required spreadsheet column: {$name}\n");
        exit(1);
    }
}

$sectionColumns = [];
$ignoredColumns = [
    'id', 'name', 'File Name', 'kind', 'medium', 'year', 'size', 'gallery', 'latitude', 'longitude',
    'place_name', 'locality', 'region', 'country', 'notes', 'url', 'rotator',
];

foreach ($headers as $index => $header) {
    $header = trim((string) $header);

    if ($header === '' || in_array($header, $ignoredColumns, true)) {
        continue;
    }

    $sectionColumns[$header] = $index;
}

$audit = json_decode((string) file_get_contents($auditPath), true);
if (!is_array($audit) || empty($audit['matched_sample']) && !isset($audit['matched'])) {
    // The audit output stores full matched rows in matched_sample only for display in earlier versions.
    // Rebuild matching from the spreadsheet and inventory audit details if full matched list is absent.
}

$matchAudit = json_decode((string) file_get_contents($auditPath), true);
$matchedByRow = [];

if (isset($matchAudit['matched']) && is_array($matchAudit['matched'])) {
    foreach ($matchAudit['matched'] as $match) {
        $matchedByRow[(int) $match['spreadsheet_row']] = $match;
    }
} else {
    // Older audit result intentionally kept only sample in stdout, but the JSON file from the patched
    // audit script contains full matched data under matched_sample only if not re-run. Force re-run.
    fwrite(STDERR, "Audit file does not contain full matched rows. Re-run audit_bxiie_spreadsheet_matches.php after applying the suffix-aware patch.\n");
    exit(1);
}

$sectionIds = [];
foreach (array_keys($sectionColumns) as $sectionName) {
    $sectionIds[$sectionName] = ensureSection($pdo, $tenant->tenantId, $sectionName, slugify($sectionName), $dryRun);
}

$counts = [
    'matched_rows' => count($matchedByRow),
    'imported' => 0,
    'skipped_missing_match' => 0,
    'sections_created_or_found' => count($sectionIds),
    'assignments' => 0,
];

if (!$dryRun) {
    $pdo->beginTransaction();
}

try {
    foreach ($rows as $rowIndex => $row) {
        $spreadsheetRowNumber = $rowIndex + 1;
        if ($rowIndex < 2) {
            continue;
        }

        $fileName = trim((string) ($row[$columns['file']] ?? ''));
        if ($fileName === '') {
            continue;
        }

        $match = $matchedByRow[$spreadsheetRowNumber] ?? null;
        if (!$match) {
            $counts['skipped_missing_match']++;
            continue;
        }

        $title = trim((string) ($row[$columns['name']] ?? ''));
        if ($title === '') {
            $title = pathinfo($fileName, PATHINFO_FILENAME);
        }

        $medium = trim((string) ($row[$columns['medium']] ?? ''));
        $year = trim((string) ($row[$columns['year']] ?? ''));
        $notesIndex = array_search('notes', $headers, true);
        $notes = $notesIndex !== false ? trim((string) ($row[$notesIndex] ?? '')) : '';

        $legacyRelative = (string) ($match['chosen_legacy_path'] ?? '');
        $sourcePath = $legacySource . '/' . ltrim($legacyRelative, '/');

        if (!is_file($sourcePath)) {
            fwrite(STDERR, "Missing source file for row {$spreadsheetRowNumber}: {$sourcePath}\n");
            $counts['skipped_missing_match']++;
            continue;
        }

        $mediaId = importMedia($pdo, $tenant->tenantId, $tenant->slug, $sourcePath, $legacyRelative, $title, $notes, $dryRun);
        $artworkId = upsertArtwork($pdo, $tenant->tenantId, $mediaId, $title, $fileName, $medium, $year, $notes, $dryRun);

        foreach ($sectionColumns as $sectionName => $columnIndex) {
            $value = strtolower(trim((string) ($row[$columnIndex] ?? '')));
            if (in_array($value, ['1', 'x', 'yes', 'true', 'y'], true)) {
                assignSection($pdo, $artworkId, $sectionIds[$sectionName], $dryRun);
                $counts['assignments']++;
            }
        }

        $counts['imported']++;
    }

    if (!$dryRun) {
        $pdo->commit();
    }
} catch (Throwable $e) {
    if (!$dryRun) {
        $pdo->rollBack();
    }

    throw $e;
}

echo json_encode([
    'ok' => true,
    'dry_run' => $dryRun,
    'tenant_id' => $tenant->tenantId,
    'counts' => $counts,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

/**
 * @return list<list<string|int|float|null>>
 */

function normalizedImageKey(string $fileName): string
{
    $base = strtolower(pathinfo($fileName, PATHINFO_FILENAME));
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $base = preg_replace('/(?:_|\-)(?:lg|large|xl|xlarge|full|web|sm|small|thumb|thumbnail|med|medium)$/', '', $base) ?? $base;

    return $base . '.' . $extension;
}

function readXlsxRows(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException("Could not open XLSX file: {$path}");
    }

    $sharedStrings = readSharedStrings($zip);
    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

    if ($workbookXml === false || $relsXml === false) {
        throw new RuntimeException('Invalid XLSX workbook structure.');
    }

    $sheetTarget = firstWorksheetTarget($workbookXml, $relsXml);
    $sheetXml = $zip->getFromName('xl/' . ltrim($sheetTarget, '/'));

    if ($sheetXml === false) {
        throw new RuntimeException("Could not read worksheet: {$sheetTarget}");
    }

    $xml = new SimpleXMLElement($sheetXml);
    $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

    $rows = [];
    foreach ($xml->sheetData->row as $rowNode) {
        $rowValues = [];
        foreach ($rowNode->c as $cell) {
            $ref = (string) $cell['r'];
            $columnIndex = columnIndexFromCellRef($ref);
            while (count($rowValues) < $columnIndex) {
                $rowValues[] = null;
            }
            $rowValues[] = readCellValue($cell, $sharedStrings);
        }
        $rows[] = $rowValues;
    }

    $zip->close();

    return $rows;
}

/**
 * @return list<string>
 */
function readSharedStrings(ZipArchive $zip): array
{
    $xmlText = $zip->getFromName('xl/sharedStrings.xml');
    if ($xmlText === false) {
        return [];
    }

    $xml = new SimpleXMLElement($xmlText);
    $strings = [];

    foreach ($xml->si as $si) {
        $parts = [];
        if (isset($si->t)) {
            $parts[] = (string) $si->t;
        }
        if (isset($si->r)) {
            foreach ($si->r as $run) {
                $parts[] = (string) $run->t;
            }
        }
        $strings[] = implode('', $parts);
    }

    return $strings;
}

function firstWorksheetTarget(string $workbookXml, string $relsXml): string
{
    $workbook = new SimpleXMLElement($workbookXml);
    $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

    $firstSheet = $workbook->sheets->sheet[0] ?? null;
    if ($firstSheet === null) {
        throw new RuntimeException('Workbook has no sheets.');
    }

    $attributes = $firstSheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
    $relationshipId = (string) ($attributes['id'] ?? '');

    $rels = new SimpleXMLElement($relsXml);
    foreach ($rels->Relationship as $rel) {
        if ((string) $rel['Id'] === $relationshipId) {
            return (string) $rel['Target'];
        }
    }

    throw new RuntimeException('Could not locate first worksheet relationship.');
}

function readCellValue(SimpleXMLElement $cell, array $sharedStrings): string|int|float|null
{
    $type = (string) ($cell['t'] ?? '');
    $raw = isset($cell->v) ? (string) $cell->v : null;

    if ($raw === null) {
        return null;
    }

    if ($type === 's') {
        return $sharedStrings[(int) $raw] ?? '';
    }

    if ($type === 'inlineStr') {
        return isset($cell->is->t) ? (string) $cell->is->t : '';
    }

    if (is_numeric($raw)) {
        return str_contains($raw, '.') ? (float) $raw : (int) $raw;
    }

    return $raw;
}

function columnIndexFromCellRef(string $ref): int
{
    preg_match('/^[A-Z]+/', strtoupper($ref), $matches);
    $letters = $matches[0] ?? 'A';
    $index = 0;

    foreach (str_split($letters) as $letter) {
        $index = ($index * 26) + (ord($letter) - ord('A') + 1);
    }

    return $index - 1;
}


function ensureSection(PDO $pdo, int $tenantId, string $name, string $slug, bool $dryRun): int
{
    $stmt = $pdo->prepare('SELECT id FROM portfolio_sections WHERE tenant_id = :tenant_id AND slug = :slug LIMIT 1');
    $stmt->execute(['tenant_id' => $tenantId, 'slug' => $slug]);
    $existing = $stmt->fetchColumn();

    if ($existing) {
        return (int) $existing;
    }

    if ($dryRun) {
        return crc32($slug);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO portfolio_sections (uuid, tenant_id, name, slug, show_as_tab, sort_order, status, created_at, updated_at)
         VALUES (UUID(), :tenant_id, :name, :slug, 1, 0, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
    );
    $stmt->execute(['tenant_id' => $tenantId, 'name' => $name, 'slug' => $slug]);

    return (int) $pdo->lastInsertId();
}

function importMedia(PDO $pdo, int $tenantId, string $tenantSlug, string $sourcePath, string $legacyRelative, string $title, string $caption, bool $dryRun): int
{
    $hash = hash_file('sha256', $sourcePath);
    $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    $relativePath = "storage/uploads/artwork/{$tenantSlug}/legacy/{$hash}.{$extension}";
    $absolutePath = dirname(__DIR__, 2) . '/' . $relativePath;

    $stmt = $pdo->prepare('SELECT id FROM media_assets WHERE tenant_id = :tenant_id AND storage_path = :storage_path LIMIT 1');
    $stmt->execute(['tenant_id' => $tenantId, 'storage_path' => $relativePath]);
    $existing = $stmt->fetchColumn();

    if (!$dryRun) {
        if (!is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0775, true);
        }

        if (!is_file($absolutePath)) {
            copy($sourcePath, $absolutePath);
        }
    }

    $dimensions = @getimagesize($sourcePath);
    $width = is_array($dimensions) ? (int) ($dimensions[0] ?? 0) : null;
    $height = is_array($dimensions) ? (int) ($dimensions[1] ?? 0) : null;
    $mime = is_array($dimensions) ? (string) ($dimensions['mime'] ?? '') : mime_content_type($sourcePath);
    $size = filesize($sourcePath) ?: null;

    if ($existing) {
        if (!$dryRun) {
            $stmt = $pdo->prepare(
                "UPDATE media_assets
                 SET original_filename = :original_filename,
                     mime_type = :mime_type,
                     file_size_bytes = :file_size_bytes,
                     width = :width,
                     height = :height,
                     alt_text = :alt_text,
                     title = :title,
                     caption = :caption,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id"
            );
            $stmt->execute([
                'original_filename' => basename($legacyRelative),
                'mime_type' => $mime,
                'file_size_bytes' => $size,
                'width' => $width,
                'height' => $height,
                'alt_text' => $title,
                'title' => $title,
                'caption' => $caption !== '' ? $caption : null,
                'id' => (int) $existing,
            ]);
        }

        return (int) $existing;
    }

    if ($dryRun) {
        return (int) crc32($relativePath);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO media_assets (
            uuid, tenant_id, original_filename, storage_path, mime_type, file_size_bytes,
            width, height, alt_text, title, caption, is_private, created_at, updated_at
         ) VALUES (
            UUID(), :tenant_id, :original_filename, :storage_path, :mime_type, :file_size_bytes,
            :width, :height, :alt_text, :title, :caption, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
         )"
    );
    $stmt->execute([
        'tenant_id' => $tenantId,
        'original_filename' => basename($legacyRelative),
        'storage_path' => $relativePath,
        'mime_type' => $mime,
        'file_size_bytes' => $size,
        'width' => $width,
        'height' => $height,
        'alt_text' => $title,
        'title' => $title,
        'caption' => $caption !== '' ? $caption : null,
    ]);

    return (int) $pdo->lastInsertId();
}

function upsertArtwork(PDO $pdo, int $tenantId, int $mediaId, string $title, string $fileName, string $medium, string $year, string $notes, bool $dryRun): int
{
    $slug = slugify($title . '-' . pathinfo($fileName, PATHINFO_FILENAME));

    $stmt = $pdo->prepare('SELECT id FROM artworks WHERE tenant_id = :tenant_id AND slug = :slug LIMIT 1');
    $stmt->execute(['tenant_id' => $tenantId, 'slug' => $slug]);
    $existing = $stmt->fetchColumn();

    if ($existing) {
        if (!$dryRun) {
            $stmt = $pdo->prepare(
                "UPDATE artworks
                 SET primary_media_id = :primary_media_id,
                     title = :title,
                     description = :description,
                     medium = :medium,
                     year_created = :year_created,
                     status = 'published',
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id"
            );
            $stmt->execute([
                'primary_media_id' => $mediaId,
                'title' => $title,
                'description' => $notes !== '' ? $notes : null,
                'medium' => $medium !== '' ? $medium : null,
                'year_created' => $year !== '' ? $year : null,
                'id' => (int) $existing,
            ]);
        }

        return (int) $existing;
    }

    if ($dryRun) {
        return (int) crc32($slug);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO artworks (
            uuid, tenant_id, primary_media_id, title, slug, description, medium, year_created,
            status, sale_status, price, sort_order, created_at, updated_at
        ) VALUES (
            UUID(), :tenant_id, :primary_media_id, :title, :slug, :description, :medium, :year_created,
            'published', 'nfs', NULL, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
        )"
    );
    $stmt->execute([
        'tenant_id' => $tenantId,
        'primary_media_id' => $mediaId,
        'title' => $title,
        'slug' => $slug,
        'description' => $notes !== '' ? $notes : null,
        'medium' => $medium !== '' ? $medium : null,
        'year_created' => $year !== '' ? $year : null,
    ]);

    return (int) $pdo->lastInsertId();
}

function assignSection(PDO $pdo, int $artworkId, int $sectionId, bool $dryRun): void
{
    if ($dryRun) {
        return;
    }

    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO artwork_section_assignments (artwork_id, section_id, sort_order, created_at)
         VALUES (:artwork_id, :section_id, 0, CURRENT_TIMESTAMP)"
    );
    $stmt->execute(['artwork_id' => $artworkId, 'section_id' => $sectionId]);
}

function slugify(string $value): string
{
    $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $value), '-'));

    return $slug !== '' ? $slug : 'artwork';
}

// End of file.
