<?php

declare(strict_types=1);

/**
 * Export the current artwork setup for the tenant whose slug is "training".
 *
 * This script is read-only. It discovers artwork-related tables from
 * information_schema, scopes rows to the training tenant, excludes binary and
 * secret-bearing columns, and writes a structured JSON report.
 */

use App\Support\Database;

const TRAINING_SLUG = 'training';

/**
 * Terminate with a clear operational error.
 */
function fail(string $message): never
{
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
    exit(1);
}

/**
 * Load an environment file without overriding values already in the process.
 */
function loadEnvironmentFile(string $path): void
{
    if (!is_readable($path)) {
        fail("Environment file is not readable: {$path}");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        fail("Unable to read environment file: {$path}");
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, 7));
        }

        $separator = strpos($line, '=');
        if ($separator === false) {
            continue;
        }

        $name = trim(substr($line, 0, $separator));
        $value = trim(substr($line, $separator + 1));

        if ($name === '' || getenv($name) !== false) {
            continue;
        }

        if (
            strlen($value) >= 2
            && (($value[0] === '"' && $value[strlen($value) - 1] === '"')
                || ($value[0] === "'" && $value[strlen($value) - 1] === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

/**
 * Quote an identifier previously obtained from information_schema.
 */
function qi(string $identifier): string
{
    if (preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
        throw new RuntimeException("Unsafe SQL identifier: {$identifier}");
    }

    return '`' . $identifier . '`';
}

/**
 * Return table column metadata keyed by column name.
 *
 * @return array<string,array<string,mixed>>
 */
function columnsFor(PDO $pdo, string $database, string $table): array
{
    $statement = $pdo->prepare(
        'SELECT column_name, data_type, column_type, is_nullable, column_default,
                column_key, extra, ordinal_position
         FROM information_schema.columns
         WHERE table_schema = :database
           AND table_name = :table
         ORDER BY ordinal_position'
    );
    $statement->execute(['database' => $database, 'table' => $table]);

    $columns = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $columns[(string) $row['column_name']] = $row;
    }

    return $columns;
}

/**
 * Exclude binary data and likely secrets from the exported report.
 */
function includeColumn(string $name, string $dataType): bool
{
    if (in_array(strtolower($dataType), [
        'binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob',
    ], true)) {
        return false;
    }

    $lower = strtolower($name);
    foreach (['password', 'secret', 'token', 'api_key', 'private_key', 'credential', 'session'] as $fragment) {
        if (str_contains($lower, $fragment)) {
            return false;
        }
    }

    return true;
}

/**
 * Fetch scoped rows from one table.
 *
 * @param array<string,array<string,mixed>> $columns
 * @param array<string,mixed> $parameters
 * @return list<array<string,mixed>>
 */
function fetchScopedRows(
    PDO $pdo,
    string $table,
    array $columns,
    string $where,
    array $parameters
): array {
    $selected = [];
    foreach ($columns as $name => $metadata) {
        if (includeColumn($name, (string) $metadata['data_type'])) {
            $selected[] = qi($name);
        }
    }

    if ($selected === []) {
        return [];
    }

    $order = isset($columns['id']) ? ' ORDER BY `id` ASC' : '';
    $sql = sprintf(
        'SELECT %s FROM %s WHERE %s%s LIMIT 5000',
        implode(', ', $selected),
        qi($table),
        $where,
        $order
    );

    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Return unique integer IDs from exported rows.
 *
 * @param list<array<string,mixed>> $rows
 * @return list<int>
 */
function idsFromRows(array $rows): array
{
    $ids = [];
    foreach ($rows as $row) {
        if (isset($row['id']) && is_numeric($row['id'])) {
            $ids[] = (int) $row['id'];
        }
    }

    return array_values(array_unique($ids));
}

/**
 * Build a named-placeholder IN clause.
 *
 * @param list<int> $values
 * @return array{sql:string,parameters:array<string,int>}
 */
function inClause(string $prefix, array $values): array
{
    $tokens = [];
    $parameters = [];

    foreach (array_values($values) as $index => $value) {
        $name = $prefix . $index;
        $tokens[] = ':' . $name;
        $parameters[$name] = $value;
    }

    return ['sql' => implode(', ', $tokens), 'parameters' => $parameters];
}

$root = getenv('ARTSFOLIO_ROOT');
if ($root === false || trim($root) === '') {
    $root = dirname(__DIR__, 2);
}
$root = rtrim((string) $root, '/');

$environmentFile = getenv('ARTSFOLIO_ENV_FILE');
if ($environmentFile !== false && trim($environmentFile) !== '') {
    loadEnvironmentFile((string) $environmentFile);
}

$autoload = $root . '/vendor/autoload.php';
if (!is_readable($autoload)) {
    fail("Composer autoloader is not readable: {$autoload}");
}
require $autoload;

try {
    $pdo = Database::connect($root);
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($database === '') {
        throw new RuntimeException('Unable to determine the current database.');
    }

    $tenantStatement = $pdo->prepare('SELECT * FROM tenants WHERE slug = :slug ORDER BY id ASC');
    $tenantStatement->execute(['slug' => TRAINING_SLUG]);
    $tenants = $tenantStatement->fetchAll(PDO::FETCH_ASSOC);

    if (count($tenants) !== 1) {
        throw new RuntimeException(sprintf(
            'Expected exactly one tenant with slug "%s"; found %d.',
            TRAINING_SLUG,
            count($tenants)
        ));
    }

    $tenant = $tenants[0];
    $tenantId = (int) $tenant['id'];

    $tableStatement = $pdo->prepare(
        'SELECT table_name
         FROM information_schema.tables
         WHERE table_schema = :database
           AND table_type = "BASE TABLE"
         ORDER BY table_name'
    );
    $tableStatement->execute(['database' => $database]);
    $allTables = array_map(
        static fn (array $row): string => (string) $row['table_name'],
        $tableStatement->fetchAll(PDO::FETCH_ASSOC)
    );

    $candidateTables = array_values(array_filter(
        $allTables,
        static fn (string $table): bool => preg_match(
            '/(?:artwork|portfolio|section|curation|variant|inventory|shipping|media|image)/i',
            $table
        ) === 1
    ));

    $report = [
        'generated_at_utc' => gmdate(DATE_ATOM),
        'database' => $database,
        'tenant' => [
            'id' => $tenantId,
            'slug' => (string) $tenant['slug'],
            'name' => $tenant['name'] ?? null,
            'status' => $tenant['status'] ?? null,
        ],
        'summary' => [
            'candidate_tables' => $candidateTables,
            'exported_tables' => [],
            'skipped_tables' => [],
        ],
        'tables' => [],
    ];

    $artworkIds = [];
    $sectionIds = [];

    foreach ($candidateTables as $table) {
        $columns = columnsFor($pdo, $database, $table);
        if (!isset($columns['tenant_id'])) {
            continue;
        }

        $rows = fetchScopedRows(
            $pdo,
            $table,
            $columns,
            '`tenant_id` = :tenant_id',
            ['tenant_id' => $tenantId]
        );

        $report['tables'][$table] = [
            'scope' => 'tenant_id',
            'columns' => $columns,
            'row_count' => count($rows),
            'rows' => $rows,
        ];
        $report['summary']['exported_tables'][] = $table;

        if ($table === 'artworks' || str_ends_with($table, '_artworks')) {
            $artworkIds = array_values(array_unique(array_merge($artworkIds, idsFromRows($rows))));
        }
        if ($table === 'sections' || str_contains($table, 'section')) {
            $sectionIds = array_values(array_unique(array_merge($sectionIds, idsFromRows($rows))));
        }
    }

    foreach ($candidateTables as $table) {
        if (isset($report['tables'][$table])) {
            continue;
        }

        $columns = columnsFor($pdo, $database, $table);

        if (isset($columns['artwork_id']) && $artworkIds !== []) {
            $clause = inClause('artwork_', $artworkIds);
            $rows = fetchScopedRows(
                $pdo,
                $table,
                $columns,
                '`artwork_id` IN (' . $clause['sql'] . ')',
                $clause['parameters']
            );
            $report['tables'][$table] = [
                'scope' => 'artwork_id',
                'columns' => $columns,
                'row_count' => count($rows),
                'rows' => $rows,
            ];
            $report['summary']['exported_tables'][] = $table;
            continue;
        }

        if (isset($columns['section_id']) && $sectionIds !== []) {
            $clause = inClause('section_', $sectionIds);
            $rows = fetchScopedRows(
                $pdo,
                $table,
                $columns,
                '`section_id` IN (' . $clause['sql'] . ')',
                $clause['parameters']
            );
            $report['tables'][$table] = [
                'scope' => 'section_id',
                'columns' => $columns,
                'row_count' => count($rows),
                'rows' => $rows,
            ];
            $report['summary']['exported_tables'][] = $table;
            continue;
        }

        $report['summary']['skipped_tables'][] = [
            'table' => $table,
            'reason' => 'No safe tenant_id, artwork_id, or section_id scope was available.',
            'columns' => array_keys($columns),
        ];
    }

    sort($report['summary']['exported_tables']);

    $json = json_encode(
        $report,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
    );
    if ($json === false) {
        throw new RuntimeException('Unable to encode the report as JSON.');
    }

    $output = getenv('ARTSFOLIO_TRAINING_ARTWORK_REPORT');
    if ($output === false || trim($output) === '') {
        $output = '/tmp/artsfolio-training-artwork-inventory-' . gmdate('Ymd-His') . '.json';
    }

    $outputDirectory = dirname((string) $output);
    if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0770, true) && !is_dir($outputDirectory)) {
        throw new RuntimeException("Unable to create report directory: {$outputDirectory}");
    }

    if (file_put_contents((string) $output, $json . PHP_EOL) === false) {
        throw new RuntimeException("Unable to write report: {$output}");
    }

    echo "[PASS] Training tenant resolved: ID {$tenantId}, slug training." . PHP_EOL;
    echo sprintf(
        "[PASS] Exported %d artwork-related tables.",
        count($report['summary']['exported_tables'])
    ) . PHP_EOL;
    echo "[PASS] Report written to: {$output}" . PHP_EOL;
    echo "[SHARE] Copy the JSON report back for metadata planning." . PHP_EOL;
} catch (Throwable $exception) {
    fail($exception->getMessage());
}

// End of file.
