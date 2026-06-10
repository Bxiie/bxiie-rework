<?php

declare(strict_types=1);

/**
 * Manual verification script for platform background job list repository.
 */

use App\Platform\Jobs\JobAdminRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$repo = new JobAdminRepository(Database::connect($root));

echo json_encode([
    'latest_count' => count($repo->latest(limit: 10)),
    'queued_count' => count($repo->latest(status: 'queued', limit: 10)),
    'domain_verify_count' => count($repo->latest(jobType: 'custom_domain.verify_dns', limit: 10)),
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
