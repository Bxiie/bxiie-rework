<?php

declare(strict_types=1);

/**
 * Manual verification script for tenant email signup consent updates.
 */

use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;
use App\Tenant\Signup\EmailSignupRepository;

$root = dirname(__DIR__, 2);
require_once __DIR__ . '/TestEnvironment.php';
TestEnvironment::skipIfProduction(basename(__FILE__));
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$tenant = (new TenantResolver($pdo))->resolveFromHost('bxiie.com');

if (!$tenant) {
    fwrite(STDERR, "Missing expected tenant for this test.\n");
    exit(1);
}

$repo = new EmailSignupRepository($pdo);
$repo->upsert(
    tenant: $tenant,
    email: 'consent-status@example.test',
    name: 'Consent Status Test',
    source: 'manual-test',
    ipAddress: '127.0.0.1',
    userAgent: 'manual-test',
);

$signupId = 0;
foreach ($repo->latestForTenant($tenant, 100) as $row) {
    if ($row['email'] === 'consent-status@example.test') {
        $signupId = (int) $row['id'];
        break;
    }
}

if ($signupId <= 0) {
    fwrite(STDERR, "Could not locate signup row.\n");
    exit(1);
}

$repo->updateConsentStatus($tenant, $signupId, 'confirmed');
$repo->updateConsentStatus($tenant, $signupId, 'unsubscribed');

echo json_encode([
    'signup_id' => $signupId,
    'latest' => $repo->latestForTenant($tenant, 5),
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
