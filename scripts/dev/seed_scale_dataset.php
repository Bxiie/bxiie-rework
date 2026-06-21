<?php

// Seeds and removes isolated scale-test tenants without touching real tenant data.

declare(strict_types=1);

use App\Platform\ScaleTesting\ScaleTenantFixtureService;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$options = parseOptions($argv);
$operation = (string) ($options['operation'] ?? 'seed');
$tenantCount = boundedInt((string) ($options['tenants'] ?? '1000'), 0, 5000, 1000);
$artworksPerTenant = boundedInt((string) ($options['artworks-per-tenant'] ?? '20'), 0, 500, 20);
$eventsPerTenant = boundedInt((string) ($options['events-per-tenant'] ?? '0'), 0, 5000, 0);

if (($options['help'] ?? false) === true) {
    usage();
    exit(0);
}

if (!in_array($operation, ['seed', 'cleanup', 'reset'], true)) {
    fwrite(STDERR, "Unsupported operation: {$operation}\n");
    usage();
    exit(1);
}

requireNonProductionSafety($root, $options);

$fixtures = new ScaleTenantFixtureService($pdo, $root);

if ($operation === 'cleanup' || $operation === 'reset') {
    $summary = $fixtures->cleanup();
    echo 'Removed scale tenants: ' . (int) ($summary['removed'] ?? 0) . "\n";
}

if ($operation === 'seed' || $operation === 'reset') {
    echo "Seeding {$tenantCount} isolated scale tenants.\n";
    $summary = $fixtures->seed($tenantCount, $artworksPerTenant, $eventsPerTenant);
    echo 'Seeded or updated scale tenants: ' . (int) ($summary['seeded_or_updated'] ?? 0) . "\n";
}

printSummary($fixtures->summary());

/**
 * Parses --key=value arguments and bare operations.
 *
 * @param array<int,string> $argv
 * @return array<string,string|bool>
 */
function parseOptions(array $argv): array
{
    $options = [];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            $options['help'] = true;
            continue;
        }

        if (str_starts_with($arg, '--')) {
            $parts = explode('=', substr($arg, 2), 2);
            $options[$parts[0]] = $parts[1] ?? true;
            continue;
        }

        if (!isset($options['operation'])) {
            $options['operation'] = $arg;
        }
    }

    return $options;
}

function usage(): void
{
    echo "Usage:\n";
    echo "  php scripts/dev/seed_scale_dataset.php seed --tenants=1000 --artworks-per-tenant=50 --events-per-tenant=200\n";
    echo "  php scripts/dev/seed_scale_dataset.php cleanup\n";
    echo "  php scripts/dev/seed_scale_dataset.php reset --tenants=1000 --artworks-per-tenant=50\n";
    echo "\nScale tenants are identified by slug prefix '" . ScaleTenantFixtureService::SLUG_PREFIX . "' and tenant_settings." . ScaleTenantFixtureService::MARKER_KEY . ".\n";
}

function boundedInt(string $value, int $min, int $max, int $default): int
{
    if (!preg_match('/^\d+$/', $value)) {
        return $default;
    }

    return max($min, min($max, (int) $value));
}

/**
 * Blocks accidental CLI use against production-looking environments unless explicitly overridden.
 */
function requireNonProductionSafety(string $root, array $options): void
{
    $appEnv = strtolower((string) (getenv('APP_ENV') ?: getenv('ARTSFOLIO_ENV') ?: ''));
    $envFile = str_replace('\\', '/', (string) (getenv('ARTSFOLIO_ENV_FILE') ?: ''));
    $normalizedRoot = str_replace('\\', '/', $root);
    $looksProduction = $appEnv === 'production'
        || $appEnv === 'prod'
        || str_contains($envFile, '/etc/artsfolio/')
        || $normalizedRoot === '/var/www/artsfolio';

    if (!$looksProduction || (($options['allow-production-like'] ?? false) === true)) {
        return;
    }

    fwrite(STDERR, "Refusing to seed or cleanup scale data in a production-looking environment.\n");
    fwrite(STDERR, "Pass --allow-production-like only for a disposable staging database.\n");
    exit(1);
}

/**
 * Prints current fixture counts in a stable format for scripts and humans.
 *
 * @param array<string,int|string> $summary
 */
function printSummary(array $summary): void
{
    echo 'Scale fixture marker: ' . $summary['marker_key'] . '=' . $summary['marker_value'] . "\n";
    echo 'Scale fixture slug prefix: ' . $summary['slug_prefix'] . "\n";
    echo 'Scale fixture tenants present: ' . (int) $summary['tenants'] . "\n";
    echo 'Scale fixture artworks present: ' . (int) $summary['artworks'] . "\n";
    echo 'Scale fixture media assets present: ' . (int) $summary['media_assets'] . "\n";
    echo 'Scale fixture analytics events present: ' . (int) $summary['analytics_events'] . "\n";
}

// End of file.
