<?php

declare(strict_types=1);

use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;

require dirname(__DIR__, 2) . '/bootstrap/app.php';

$options = getopt('', ['csv:', 'tenant::', 'host::', 'dry-run::']);

$root = dirname(__DIR__, 2);
$csvPath = $root . '/' . ltrim((string) ($options['csv'] ?? ''), '/');
$host = (string) ($options['host'] ?? 'bxiie.com');
$tenantSlug = (string) ($options['tenant'] ?? 'bxiie');
$dryRun = array_key_exists('dry-run', $options);

if (!is_file($csvPath)) {
    fwrite(STDERR, "Missing CSV: {$csvPath}\n");
    exit(1);
}

$pdo = Database::connect($root);
$tenant = (new TenantResolver($pdo))->resolveFromHost($host);

if (!$tenant || $tenant->slug !== $tenantSlug) {
    fwrite(STDERR, "Could not resolve tenant {$tenantSlug} from host {$host}.\n");
    exit(1);
}

$rows = readCsv($csvPath);

if (!$dryRun) {
    $pdo->beginTransaction();
}

try {
    if (!$dryRun) {
        $stmt = $pdo->prepare("DELETE FROM exhibitions WHERE tenant_id = :tenant_id");
        $stmt->execute(['tenant_id' => $tenant->tenantId]);
    }

    $count = 0;
    foreach ($rows as $index => $row) {
        if ($index === 0) {
            continue;
        }

        $date = trim((string) ($row[0] ?? ''));
        $name = trim((string) ($row[1] ?? ''));
        $city = trim((string) ($row[2] ?? ''));
        $state = trim((string) ($row[3] ?? ''));
        $work = trim((string) ($row[4] ?? ''));
        $note1 = trim((string) ($row[5] ?? ''));
        $note2 = trim((string) ($row[6] ?? ''));

        if ($date === '' && $name === '' && $work === '') {
            continue;
        }

        if ($name === '') {
            $name = 'Untitled event';
        }

        $location = trim(implode(', ', array_filter([$city, $state])));

        if (!$dryRun) {
            $stmt = $pdo->prepare(
                "INSERT INTO exhibitions (
                    uuid, tenant_id, exhibition_date, name, exhibition_type, location,
                    city, state_region, work_name, notes, sort_order, status, created_at, updated_at
                ) VALUES (
                    UUID(), :tenant_id, :exhibition_date, :name, :exhibition_type, :location,
                    :city, :state_region, :work_name, :notes, :sort_order, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )"
            );

            $stmt->execute([
                'tenant_id' => $tenant->tenantId,
                'exhibition_date' => $date ?: null,
                'name' => $name,
                'exhibition_type' => $note1 ?: null,
                'location' => $location ?: null,
                'city' => $city ?: null,
                'state_region' => $state ?: null,
                'work_name' => $work ?: null,
                'notes' => $note2 ?: null,
                'sort_order' => $count,
            ]);
        }

        $count++;
    }

    if (!$dryRun) {
        $pdo->commit();
    }

    echo json_encode([
        'ok' => true,
        'dry_run' => $dryRun,
        'tenant_id' => $tenant->tenantId,
        'imported' => $count,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    if (!$dryRun) {
        $pdo->rollBack();
    }

    throw $e;
}

function readCsv(string $path): array
{
    $handle = fopen($path, 'rb');
    if (!$handle) {
        throw new RuntimeException("Unable to open {$path}");
    }

    $rows = [];
    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = $row;
    }

    fclose($handle);

    return $rows;
}

// End of file.
