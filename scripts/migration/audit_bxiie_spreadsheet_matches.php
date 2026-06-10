<?php

declare(strict_types=1);

$options = getopt('', ['xlsx:', 'inventory:', 'output::']);

$root = dirname(__DIR__, 2);
$xlsxPath = $root . '/' . ltrim((string) ($options['xlsx'] ?? ''), '/');
$inventoryPath = $root . '/' . ltrim((string) ($options['inventory'] ?? ''), '/');
$outputPath = $root . '/' . ltrim((string) ($options['output'] ?? 'storage/imports/bxiie-spreadsheet-match-audit.json'), '/');

if (!is_file($xlsxPath)) {
    fwrite(STDERR, "Missing XLSX file: {$xlsxPath}\n");
    exit(1);
}
if (!is_file($inventoryPath)) {
    fwrite(STDERR, "Missing inventory file: {$inventoryPath}\n");
    exit(1);
}

$rows = readXlsxRows($xlsxPath);
$headers = array_map(static fn ($v): string => trim((string) $v), $rows[0] ?? []);
$fileIndex = array_search('File Name', $headers, true);
$nameIndex = array_search('name', $headers, true);

if ($fileIndex === false || $nameIndex === false) {
    fwrite(STDERR, "Spreadsheet must include name and File Name columns.\n");
    exit(1);
}

$inventory = json_decode((string) file_get_contents($inventoryPath), true);
if (!is_array($inventory) || !isset($inventory['images']) || !is_array($inventory['images'])) {
    fwrite(STDERR, "Invalid inventory JSON.\n");
    exit(1);
}

$available = [];
foreach ($inventory['images'] as $image) {
    $relative = (string) ($image['relative_path'] ?? '');
    $basename = strtolower(basename($relative));

    if ($basename === '') {
        continue;
    }

    $key = normalizedImageKey($basename);
    $area = ((int) ($image['width'] ?? 0)) * ((int) ($image['height'] ?? 0));

    $available[$key][] = [
        'relative_path' => $relative,
        'basename' => $basename,
        'width' => $image['width'] ?? null,
        'height' => $image['height'] ?? null,
        'size_bytes' => $image['size_bytes'] ?? null,
        'area' => $area,
    ];
}

foreach ($available as $key => $matches) {
    usort($matches, static function (array $a, array $b): int {
        $areaCompare = ((int) $b['area']) <=> ((int) $a['area']);
        if ($areaCompare !== 0) {
            return $areaCompare;
        }

        return ((int) ($b['size_bytes'] ?? 0)) <=> ((int) ($a['size_bytes'] ?? 0));
    });

    $available[$key] = $matches;
}

$matched = [];
$missing = [];
$duplicateSpreadsheetFiles = [];
$seenSpreadsheetFiles = [];

for ($i = 2; $i < count($rows); $i++) {
    $row = $rows[$i];
    $fileName = trim((string) ($row[$fileIndex] ?? ''));
    $name = trim((string) ($row[$nameIndex] ?? ''));

    if ($fileName === '') {
        continue;
    }

    $lower = strtolower($fileName);
    $matchKey = normalizedImageKey($lower);

    if (isset($seenSpreadsheetFiles[$lower])) {
        $duplicateSpreadsheetFiles[] = [
            'file_name' => $fileName,
            'first_row' => $seenSpreadsheetFiles[$lower],
            'duplicate_row' => $i + 1,
        ];
    }
    $seenSpreadsheetFiles[$lower] = $i + 1;

    if (isset($available[$matchKey])) {
        $best = $available[$matchKey][0];

        $matched[] = [
            'spreadsheet_row' => $i + 1,
            'name' => $name,
            'file_name' => $fileName,
            'match_key' => $matchKey,
            'chosen_legacy_path' => $best['relative_path'],
            'chosen_width' => $best['width'],
            'chosen_height' => $best['height'],
            'chosen_size_bytes' => $best['size_bytes'],
            'all_legacy_matches' => $available[$matchKey],
        ];
    } else {
        $missing[] = [
            'spreadsheet_row' => $i + 1,
            'name' => $name,
            'file_name' => $fileName,
            'match_key' => $matchKey,
        ];
    }
}

$result = [
    'ok' => count($missing) === 0,
    'xlsx' => $xlsxPath,
    'inventory' => $inventoryPath,
    'counts' => [
        'spreadsheet_rows_with_file' => count($matched) + count($missing),
        'matched' => count($matched),
        'missing' => count($missing),
        'duplicate_spreadsheet_files' => count($duplicateSpreadsheetFiles),
    ],
    'missing' => $missing,
    'duplicates' => $duplicateSpreadsheetFiles,
    'matched' => $matched,
    'matched_sample' => array_slice($matched, 0, 25),
];

if (!is_dir(dirname($outputPath))) {
    mkdir(dirname($outputPath), 0775, true);
}

file_put_contents($outputPath, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

echo json_encode([
    'ok' => $result['ok'],
    'output' => $outputPath,
    'counts' => $result['counts'],
    'missing_sample' => array_slice($missing, 0, 25),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

function normalizedImageKey(string $fileName): string
{
    $base = strtolower(pathinfo($fileName, PATHINFO_FILENAME));
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $base = preg_replace('/(?:_|\-)(?:lg|large|xl|xlarge|full|web|sm|small|thumb|thumbnail|med|medium)$/', '', $base) ?? $base;

    return $base . '.' . $extension;
}

/**
 * @return list<list<string|int|float|null>>
 */
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

// End of file.
