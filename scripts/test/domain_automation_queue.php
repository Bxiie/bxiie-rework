<?php

declare(strict_types=1);

/**
 * Manual verification script for non-destructive custom-domain automation queueing.
 */

use App\Platform\Domains\DomainAutomationService;
use App\Platform\Jobs\BackgroundJobRepository;
use App\Platform\Tenancy\TenantDomainRepository;
use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;

$root = dirname(__DIR__, 2);

require $root . '/bootstrap/app.php';

$host = $argv[1] ?? 'bxiie.com';
$customDomain = $argv[2] ?? 'automation-test.example';

$pdo = Database::connect($root);
$resolver = new TenantResolver($pdo);
$tenant = $resolver->resolveFromHost($host);

if (!$tenant) {
    fwrite(STDERR, "No tenant resolved for {$host}\n");
    exit(1);
}

$service = new DomainAutomationService(
    domains: new TenantDomainRepository($pdo),
    jobs: new BackgroundJobRepository($pdo),
);

$verifyJobId = $service->requestCustomDomain($tenant, $customDomain);
$renderJobId = $service->queueVhostRender($tenant, $customDomain);

echo json_encode([
    'tenant' => $tenant->slug,
    'custom_domain' => $customDomain,
    'verify_dns_job_id' => $verifyJobId,
    'render_vhost_job_id' => $renderJobId,
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
