<?php

declare(strict_types=1);

/**
 * Static regression checks for worker health, DNS result visibility, and tenant
 * subdomain fallback. These avoid requiring a production database in preflight.
 */

$root = dirname(__DIR__, 2);

$checks = [
    'worker heartbeat uses UTC_TIMESTAMP' => [
        'file' => $root . '/app/Platform/Workers/WorkerHeartbeatRepository.php',
        'needle' => 'UTC_TIMESTAMP()',
    ],
    'worker health threshold is one-minute class' => [
        'file' => $root . '/app/Platform/Workers/WorkerHeartbeatRepository.php',
        'needle' => 'HEALTHY_AGE_SECONDS = 75',
    ],
    'admin layout displays worker warning' => [
        'file' => $root . '/app/Http/View/AdminLayout.php',
        'needle' => 'workerHealthWarning',
    ],
    'DNS result migration exists' => [
        'file' => $root . '/database/migrations/0019_tenant_domain_dns_results.sql',
        'needle' => 'dns_last_result',
    ],
    'domain page summarizes last DNS result' => [
        'file' => $root . '/app/Http/Controllers/Platform/Admin/DomainsController.php',
        'needle' => 'dnsResultSummary',
    ],
    'tenant resolver has subdomain fallback' => [
        'file' => $root . '/app/Platform/Tenancy/TenantResolver.php',
        'needle' => 'resolvePlatformSubdomainFallback',
    ],
    'platform contact renders turnstile widget' => [
        'file' => $root . '/app/Http/Controllers/Platform/MarketingController.php',
        'needle' => 'turnstile/v0/api.js',
    ],
];

foreach ($checks as $name => $check) {
    $content = file_get_contents($check['file']);
    if ($content === false || !str_contains($content, $check['needle'])) {
        fwrite(STDERR, "Failed static regression check: {$name}\n");
        exit(1);
    }
}

echo "Worker/DNS/tenant static checks passed.\n";

// End of file.
