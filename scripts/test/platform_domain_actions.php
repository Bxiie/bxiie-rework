<?php

declare(strict_types=1);

/**
 * Manual verification script for platform custom domain action service.
 */

use App\Platform\Domains\DomainAdminRepository;
use App\Platform\Domains\DomainAdminService;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$domains = (new DomainAdminRepository($pdo))->latest(1);

if (!$domains) {
    echo json_encode([
        'skipped' => true,
        'reason' => 'No tenant_domains rows exist.',
    ], JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

$domainId = (int) $domains[0]['id'];
$service = new DomainAdminService($pdo);

$verifyJobId = $service->queueDnsVerification($domainId);
$vhostJobId = $service->queueVhostRender($domainId, getenv('ARTSFOLIO_PUBLIC_ROOT') ?: '/var/www/artsfolio/public');

echo json_encode([
    'domain_id' => $domainId,
    'verify_job_id' => $verifyJobId,
    'vhost_job_id' => $vhostJobId,
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
