<?php

declare(strict_types=1);

/**
 * Manual verification script for platform background job enqueue/claim/complete behavior.
 */

use App\Platform\Jobs\BackgroundJobRepository;
use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;

$root = dirname(__DIR__, 2);

require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$resolver = new TenantResolver($pdo);
$tenant = $resolver->resolveFromHost('bxiie.com');

if (!$tenant) {
    fwrite(STDERR, "No tenant resolved for bxiie.com\n");
    exit(1);
}

$jobs = new BackgroundJobRepository($pdo);

$jobId = $jobs->enqueue(
    jobType: 'custom_domain.verify_dns',
    payload: [
        'hostname' => 'test-domain.example',
    ],
    tenantId: $tenant->tenantId,
);

$job = $jobs->claimNext();

if (!$job || (int) $job['id'] !== $jobId) {
    fwrite(STDERR, "Failed to claim expected job ID {$jobId}\n");
    exit(1);
}

$jobs->markComplete($jobId);

echo "Background job verification passed for job ID {$jobId}\n";

// End of file.
