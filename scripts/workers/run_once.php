<?php

declare(strict_types=1);

/**
 * Claims and runs one background job.
 *
 * Current behavior is intentionally dry-run only for domain automation.
 */

use App\Platform\Domains\ApacheVhostRenderer;
use App\Platform\Domains\DnsVerifier;
use App\Platform\Jobs\BackgroundJobRepository;
use App\Platform\Jobs\Handlers\RenderVhostJobHandler;
use App\Platform\Jobs\Handlers\VerifyDnsJobHandler;
use App\Support\Database;

$root = dirname(__DIR__, 2);

require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$jobs = new BackgroundJobRepository($pdo);

$job = $jobs->claimNext();

if (!$job) {
    echo "No queued jobs available.\n";
    exit(0);
}

try {
    switch ($job['job_type']) {
        case 'custom_domain.verify_dns':
            $expectedIps = array_filter(array_map("trim", explode(",", getenv("ARTSFOLIO_EXPECTED_IPV4") ?: "127.0.0.1")));
            $handler = new VerifyDnsJobHandler(new DnsVerifier($expectedIps));
            echo $handler->handle($job['payload']) . "\n";
            $jobs->markComplete((int) $job['id']);
            break;

        case 'custom_domain.render_vhost':
            $handler = new RenderVhostJobHandler(new ApacheVhostRenderer());
            echo $handler->handle($job['payload']) . "\n";
            $jobs->markComplete((int) $job['id']);
            break;

        default:
            throw new \RuntimeException("No handler for job type: {$job['job_type']}");
    }

    echo "Completed job {$job['id']} of type {$job['job_type']}.\n";
} catch (\Throwable $e) {
    $jobs->markFailed((int) $job['id'], $e->getMessage());
    fwrite(STDERR, "Failed job {$job['id']}: {$e->getMessage()}\n");
    exit(1);
}

// End of file.
