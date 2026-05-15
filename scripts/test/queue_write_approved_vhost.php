<?php

declare(strict_types=1);

/**
 * Queues a dry-run job to plan writing the latest approved vhost artifact.
 */

use App\Platform\Jobs\BackgroundJobRepository;
use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;

$root = dirname(__DIR__, 2);

require $root . '/bootstrap/app.php';

$tenantHost = $argv[1] ?? 'bxiie.com';
$customDomain = $argv[2] ?? null;

if (!$customDomain) {
    fwrite(STDERR, "Usage: php scripts/test/queue_write_approved_vhost.php bxiie.com artifact-test.example\n");
    exit(1);
}

$pdo = Database::connect($root);
$resolver = new TenantResolver($pdo);
$tenant = $resolver->resolveFromHost($tenantHost);

if (!$tenant) {
    fwrite(STDERR, "No tenant resolved for {$tenantHost}\n");
    exit(1);
}

$jobs = new BackgroundJobRepository($pdo);

$jobId = $jobs->enqueue(
    jobType: 'custom_domain.write_approved_vhost',
    payload: [
        'hostname' => $customDomain,
    ],
    tenantId: $tenant->tenantId,
);

echo "Queued write approved vhost dry-run job {$jobId} for {$customDomain}\n";

// End of file.
