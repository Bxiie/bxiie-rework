<?php
/**
 * Import Bxiie exhibition/event history from a normalized CSV export.
 *
 * Expected CSV columns:
 *   date, exhibition_name, city, state, work_name, notes, notes2
 *
 * Mapping:
 * - date -> display_date and sortable event_date
 * - exhibition_name -> title
 * - notes -> event_type
 * - city + state -> location display fields
 * - work_name -> work_name
 * - notes2 -> additional_info
 *
 * This script is intentionally destructive for the selected tenant's events:
 * it deletes existing exhibition rows before importing the CSV rows.
 *
 * Example:
 *   sudo -u www-data \
 *     DATABASE_PATH='/var/lib/bxiie-cms/database/bxiie.sqlite' \
 *     php scripts/import_events_from_resume.php \
 *       --csv=/var/tmp/events_import.csv \
 *       --tenant=bxiie
 */

declare(strict_types=1);

function usage(): void
{
    fwrite(STDERR, <<<TEXT
Usage:
  php scripts/import_events_from_resume.php --csv=/path/events_import.csv [options]

Required:
  --csv=/path/events_import.csv   CSV with date, exhibition_name, city, state, work_name, notes, notes2.

Options:
  --tenant=bxiie                  Tenant slug. Default: bxiie.
  --dry-run                       Validate and show actions without changing the database.
  --help                          Show this help.

TEXT);
}

function fail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function logLine(string $message): void
{
    fwrite(STDOUT, "[events-import] {$message}\n");
}

function optionValue(array $options, string $name, ?string $default = null): ?string
{
    return isset($options[$name]) && is_string($options[$name]) ? $options[$name] : $default;
}

function hasFlag(array $options, string $name): bool
{
    return array_key_exists($name, $options);
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

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
    foreach ($stmt->fetchAll() as $row) {
        if (($row['name'] ?? '') === $column) {
            return true;
        }
    }

    return false;
}

function ensureSchema(PDO $pdo, bool $dryRun): void
{
    $columns = [
        'display_date' => 'TEXT',
        'event_type' => 'TEXT',
        'state' => 'TEXT',
        'work_name' => 'TEXT',
        'additional_info' => 'TEXT',
    ];

    foreach ($columns as $column => $type) {
        if (columnExists($pdo, 'exhibitions', $column)) {
            continue;
        }

        $sql = "ALTER TABLE exhibitions ADD COLUMN {$column} {$type}";
        if ($dryRun) {
            logLine("Would run: {$sql}");
        } else {
            $pdo->exec($sql);
            logLine("Added exhibitions.{$column}");
        }
    }
}

function normalizeHeader(string $header): string
{
    $header = strtolower(trim($header));
    $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? '';
    return trim($header, '_');
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

    $headers = fgetcsv($handle);
    if ($headers === false) {
        fail("CSV has no header row: {$csvPath}");
    }

    $normalized = array_map('normalizeHeader', $headers);
    $required = ['date', 'exhibition_name', 'city', 'state', 'work_name', 'notes', 'notes2'];
    foreach ($required as $requiredColumn) {
        if (!in_array($requiredColumn, $normalized, true)) {
            fail("Missing required CSV column: {$requiredColumn}");
        }
    }

    $rows = [];
    while (($values = fgetcsv($handle)) !== false) {
        if (!array_filter($values, static fn($value) => trim((string) $value) !== '')) {
            continue;
        }

        $record = [];
        foreach ($normalized as $index => $header) {
            $record[$header] = trim((string) ($values[$index] ?? ''));
        }
        $rows[] = $record;
    }

    fclose($handle);

    return $rows;
}

function sortableDate(string $displayDate): string
{
    $displayDate = trim($displayDate);
    if ($displayDate === '') {
        return '1900-01-01';
    }

    $timestamp = strtotime('1 ' . $displayDate);
    if ($timestamp === false) {
        $timestamp = strtotime($displayDate);
    }

    if ($timestamp === false) {
        return '1900-01-01';
    }

    return date('Y-m-d', $timestamp);
}

function insertEvent(PDO $pdo, int $tenantId, array $row): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO exhibitions (
            tenant_id,
            title,
            venue,
            city,
            state,
            event_date,
            display_date,
            url,
            description,
            event_type,
            work_name,
            additional_info,
            is_recent
        ) VALUES (
            :tenant_id,
            :title,
            :venue,
            :city,
            :state,
            :event_date,
            :display_date,
            :url,
            :description,
            :event_type,
            :work_name,
            :additional_info,
            :is_recent
        )'
    );

    $stmt->execute([
        'tenant_id' => $tenantId,
        'title' => $row['exhibition_name'],
        'venue' => '',
        'city' => $row['city'],
        'state' => $row['state'],
        'event_date' => sortableDate($row['date']),
        'display_date' => $row['date'],
        'url' => '',
        'description' => $row['notes'],
        'event_type' => $row['notes'],
        'work_name' => $row['work_name'],
        'additional_info' => $row['notes2'],
        'is_recent' => 1,
    ]);
}

$options = getopt('', ['csv:', 'tenant::', 'dry-run', 'help']);
if ($options === false || hasFlag($options, 'help')) {
    usage();
    exit($options === false ? 1 : 0);
}

$csvPath = optionValue($options, 'csv');
if ($csvPath === null) {
    usage();
    fail('Missing required --csv option.');
}

$tenantSlug = optionValue($options, 'tenant', 'bxiie') ?? 'bxiie';
$dryRun = hasFlag($options, 'dry-run');
$databasePath = getenv('DATABASE_PATH') ?: __DIR__ . '/../database/bxiie.sqlite';

$pdo = loadPdo($databasePath);
$tenantId = tenantId($pdo, $tenantSlug);
$rows = readCsv($csvPath);

logLine("Database: {$databasePath}");
logLine("Tenant: {$tenantSlug} #{$tenantId}");
logLine("CSV rows: " . count($rows));
logLine($dryRun ? 'Dry run: yes' : 'Dry run: no');

ensureSchema($pdo, $dryRun);

if ($dryRun) {
    foreach (array_slice($rows, 0, 10) as $row) {
        $location = trim($row['city'] . ', ' . $row['state'], ', ');
        logLine("Would import: {$row['date']} | {$row['exhibition_name']} | {$row['notes']} | {$location} | {$row['work_name']}");
    }
    logLine('Dry run complete.');
    exit(0);
}

$pdo->beginTransaction();
try {
    $delete = $pdo->prepare('DELETE FROM exhibitions WHERE tenant_id = :tenant_id');
    $delete->execute(['tenant_id' => $tenantId]);

    foreach ($rows as $row) {
        insertEvent($pdo, $tenantId, $row);
    }

    $pdo->commit();
} catch (Throwable $exception) {
    $pdo->rollBack();
    throw $exception;
}

logLine('Imported events: ' . count($rows));
logLine('Complete.');

// End of file.
