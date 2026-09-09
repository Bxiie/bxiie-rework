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
use App\Platform\Email\EmailOutboxRepository;
use App\Platform\Jobs\BackgroundJobRepository;
use App\Platform\Jobs\Handlers\AnalyticsRollupJobHandler;
use App\Platform\Jobs\Handlers\RenderVhostJobHandler;
use App\Platform\Jobs\Handlers\ReleaseExpiredSalesReservationsJobHandler;
use App\Platform\Jobs\Handlers\QueueAbandonedCartEmailsJobHandler;
use App\Platform\Jobs\Handlers\ScaleTenantFixtureJobHandler;
use App\Platform\Jobs\Handlers\TenantSiteBootstrapJobHandler;
use App\Platform\Jobs\Handlers\VerifyDnsJobHandler;
use App\Platform\Jobs\Handlers\WriteApprovedVhostJobHandler;
use App\Platform\ScaleTesting\ScaleTenantFixtureService;
use App\Platform\Tenancy\TenantDomainRepository;
use App\Support\Database;
use App\Tenant\Sales\AbandonedCartEmailQueueService;
use App\Tenant\Sales\SalesRepository;
use App\Tenant\Social\InstagramClient;
use App\Tenant\Social\SocialImageService;
use App\Tenant\Social\SocialPublishingService;
use App\Tenant\Social\SocialRepository;
use App\Tenant\Social\SocialTokenCipher;
use App\Tenant\Artwork\ArtworkPublicationService;

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
            try {
                echo $handler->handle($job['payload']) . "\n";
            } finally {
                // Re-enqueue the next cycle even on failure so one transient error
                // does not permanently stop this recurring job. enqueueSingleton
                // excludes $job['id'] from its own existing-job check, so this is
                // safe to call while the current row is still 'running'.
                $jobs->enqueueSingleton('analytics.rollup', ['days' => (int) ($job['payload']['days'] ?? 3)], null, 300, (int) $job['id']);
            }
            $jobs->markComplete((int) $job['id']);
            break;

        case 'sales.cart.queue_abandoned_reminders':
            $handler = new QueueAbandonedCartEmailsJobHandler(new AbandonedCartEmailQueueService($pdo, $root));
            $interval = max(3600, (int) ($job['payload']['interval_seconds'] ?? 3600));
            $limit = max(1, min(1000, (int) ($job['payload']['limit_per_stage'] ?? 200)));
            try {
                echo $handler->handle($job['payload']) . "\n";
            } finally {
                // See analytics.rollup above: always re-enqueue the next cycle.
                $jobs->enqueueSingleton('sales.cart.queue_abandoned_reminders', ['interval_seconds' => $interval, 'limit_per_stage' => $limit], null, $interval, (int) $job['id']);
            }
            $jobs->markComplete((int) $job['id']);
            break;

        case 'sales.inventory.release_expired':
            $handler = new ReleaseExpiredSalesReservationsJobHandler(new SalesRepository($pdo));
            $interval = max(60, (int) ($job['payload']['interval_seconds'] ?? 300));
            try {
                echo $handler->handle($job['payload']) . "\n";
            } finally {
                // See analytics.rollup above: always re-enqueue the next cycle.
                // This job's handle() also retries transient deadlocks internally
                // (SalesRepository::withDeadlockRetry) before it ever reaches here.
                $jobs->enqueueSingleton('sales.inventory.release_expired', ['interval_seconds' => $interval], null, $interval, (int) $job['id']);
            }
            $jobs->markComplete((int) $job['id']);
            break;

        case 'social.publish_due':
            $interval = max(60, (int) ($job['payload']['interval_seconds'] ?? 60));
            $batchSize = max(1, min(50, (int) ($job['payload']['batch_size'] ?? 10)));
            $handler = new SocialPublishingService(
                $pdo,
                new SocialRepository($pdo),
                new SocialTokenCipher(),
                new InstagramClient(),
                new SocialImageService($root),
                new EmailOutboxRepository($pdo),
            );
            try {
                $result = $handler->publishDue($batchSize);
                echo 'Social publishing: checked=' . $result['checked'] . ', published=' . $result['published'] . ', failed=' . $result['failed'] . "\n";
            } finally {
                // Keep the social scheduler alive even after provider/network
                // failure. Per-post retry state remains in social_posts.
                $jobs->enqueueSingleton(
                    'social.publish_due',
                    ['interval_seconds' => $interval, 'batch_size' => $batchSize],
                    null,
                    $interval,
                    (int) $job['id'],
                );
            }
            $jobs->markComplete((int) $job['id']);
            break;

        case 'artwork.publish_due':
            $interval = max(60, (int) ($job['payload']['interval_seconds'] ?? 60));
            try {
                $result = (new ArtworkPublicationService($pdo))->publishDue();
                echo 'Artwork publishing: artworks=' . $result['artworks'] . ', groups=' . $result['groups'] . "\n";
            } finally {
                $jobs->enqueueSingleton('artwork.publish_due', ['interval_seconds' => $interval], null, $interval, (int) $job['id']);
            }
            $jobs->markComplete((int) $job['id']);
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
    $jobs->releaseExecutionLock((int) $job['id']);
} catch (\Throwable $e) {
    $jobs->markFailed((int) $job['id'], $e->getMessage());
    artsfolio_worker_heartbeat($workerName, 'failed', ['job_id' => (int) $job['id'], 'error' => $e->getMessage()]);
    fwrite(STDERR, "Failed job {$job['id']}: {$e->getMessage()}\n");
    $jobs->releaseExecutionLock((int) $job['id']);
    exit(1);
}

// End of file.
