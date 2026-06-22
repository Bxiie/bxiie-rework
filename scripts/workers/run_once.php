<?php

declare(strict_types=1);

/**
 * Claims and runs one background job.
 *
 * Current behavior is intentionally dry-run only for domain automation.
 */

use App\Platform\Analytics\AnalyticsRollupService;
use App\Platform\Domains\ApacheVhostRenderer;
use App\Platform\Domains\ApacheVhostWritePlanner;
use App\Platform\Domains\DomainArtifactRepository;
use App\Platform\Domains\DnsVerifier;
use App\Platform\Jobs\BackgroundJobRepository;
use App\Platform\Jobs\Handlers\AnalyticsRollupJobHandler;
use App\Platform\Jobs\Handlers\RenderVhostJobHandler;
use App\Platform\Jobs\Handlers\ReleaseExpiredSalesReservationsJobHandler;
use App\Platform\Jobs\Handlers\ScaleTenantFixtureJobHandler;
use App\Platform\Jobs\Handlers\TenantSiteBootstrapJobHandler;
use App\Platform\Jobs\Handlers\VerifyDnsJobHandler;
use App\Platform\Jobs\Handlers\WriteApprovedVhostJobHandler;
use App\Platform\ScaleTesting\ScaleTenantFixtureService;
use App\Platform\Tenancy\TenantDomainRepository;
use App\Support\Database;
use App\Tenant\Sales\SalesRepository;

$root = dirname(__DIR__, 2);
require_once $root . '/scripts/workers/heartbeat.php';

require $root . '/bootstrap/app.php';
$workerName = trim((string) (getenv('ARTSFOLIO_WORKER_NAME') ?: 'background-run-once'));
artsfolio_worker_heartbeat($workerName, 'alive', ['entrypoint' => 'scripts/workers/run_once.php']);

$pdo = Database::connect($root);
$jobs = new BackgroundJobRepository($pdo);
$staleMinutes = max(1, (int) (getenv('ARTSFOLIO_BACKGROUND_STALE_MINUTES') ?: 30));
$recovered = $jobs->requeueRunningOlderThanMinutes($staleMinutes);
if ($recovered > 0) {
    echo "Recovered {$recovered} stale background job(s).\n";
}

$job = $jobs->claimNext();

if (!$job) {
    artsfolio_worker_heartbeat($workerName, 'idle', ['entrypoint' => 'scripts/workers/run_once.php']);
    echo "No queued jobs available.\n";
    exit(0);
}

try {
    artsfolio_worker_heartbeat($workerName, 'running', ['job_id' => (int) $job['id'], 'job_type' => (string) $job['job_type']]);
    switch ($job['job_type']) {
        case 'custom_domain.verify_dns':
        case 'tenant.domain.verify':
            $expectedIps = array_filter(array_map("trim", explode(",", getenv("ARTSFOLIO_EXPECTED_IPV4") ?: "127.0.0.1")));
            $handler = new VerifyDnsJobHandler(new DnsVerifier($expectedIps), new TenantDomainRepository($pdo), $jobs);
            $payload = $job['payload'];
            if (isset($payload['domain']) && !isset($payload['hostname'])) {
                $payload['hostname'] = $payload['domain'];
            }
            echo $handler->handle($payload, isset($job['tenant_id']) ? (int) $job['tenant_id'] : null) . "\n";
            $jobs->markComplete((int) $job['id']);
            break;

        case 'tenant.site.bootstrap':
            $handler = new TenantSiteBootstrapJobHandler($pdo);
            echo $handler->handle($job['payload'], isset($job['tenant_id']) ? (int) $job['tenant_id'] : null) . "\n";
            $jobs->markComplete((int) $job['id']);
            break;

        case 'custom_domain.render_vhost':
            $handler = new RenderVhostJobHandler(new ApacheVhostRenderer(), new DomainArtifactRepository($pdo), new TenantDomainRepository($pdo));
            echo $handler->handle($job['payload'], isset($job['tenant_id']) ? (int) $job['tenant_id'] : null) . "\n";
            $jobs->markComplete((int) $job['id']);
            break;


        case 'scale_tenants.seed':
            $handler = new ScaleTenantFixtureJobHandler(new ScaleTenantFixtureService($pdo, $root));
            echo $handler->handle($job['payload']) . "\n";
            $jobs->markComplete((int) $job['id']);
            break;

        case 'scale_tenants.cleanup':
            $handler = new ScaleTenantFixtureJobHandler(new ScaleTenantFixtureService($pdo, $root));
            $payload = $job['payload'];
            $payload['action'] = 'cleanup';
            echo $handler->handle($payload) . "\n";
            $jobs->markComplete((int) $job['id']);
            break;


        case 'analytics.rollup':
            $handler = new AnalyticsRollupJobHandler(new AnalyticsRollupService($pdo));
            echo $handler->handle($job['payload']) . "\n";
            $jobs->markComplete((int) $job['id']);
            $jobs->enqueue('analytics.rollup', ['days' => (int) ($job['payload']['days'] ?? 3)], null, 300);
            break;

        case 'sales.inventory.release_expired':
            $handler = new ReleaseExpiredSalesReservationsJobHandler(new SalesRepository($pdo));
            echo $handler->handle($job['payload']) . "\n";
            $jobs->markComplete((int) $job['id']);
            $interval = max(60, (int) ($job['payload']['interval_seconds'] ?? 300));
            $jobs->enqueue('sales.inventory.release_expired', ['interval_seconds' => $interval], null, $interval);
            break;

        case 'custom_domain.write_approved_vhost':
            $handler = new WriteApprovedVhostJobHandler(
                new DomainArtifactRepository($pdo),
                new ApacheVhostWritePlanner()
            );

            echo $handler->handle($job['payload']) . "\n";
            $jobs->markComplete((int) $job['id']);
            break;

        default:
            throw new \RuntimeException("No handler for job type: {$job['job_type']}");
    }

    artsfolio_worker_heartbeat($workerName, 'alive', ['last_job_id' => (int) $job['id'], 'last_job_type' => (string) $job['job_type']]);
    echo "Completed job {$job['id']} of type {$job['job_type']}.\n";
} catch (\Throwable $e) {
    $jobs->markFailed((int) $job['id'], $e->getMessage());
    artsfolio_worker_heartbeat($workerName, 'failed', ['job_id' => (int) $job['id'], 'error' => $e->getMessage()]);
    fwrite(STDERR, "Failed job {$job['id']}: {$e->getMessage()}\n");
    exit(1);
}

// End of file.
