<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$runOnce = file_get_contents($root . '/scripts/workers/run_once.php');
$signup = file_get_contents($root . '/app/Platform/Signup/TenantSignupService.php');
$bootstrap = file_get_contents($root . '/app/Platform/Jobs/Handlers/TenantSiteBootstrapJobHandler.php');

$checks = [
    'run_once supports tenant.domain.verify alias' => "case 'tenant.domain.verify':",
    'run_once supports tenant.site.bootstrap' => "case 'tenant.site.bootstrap':",
    'run_once registers bootstrap handler' => 'TenantSiteBootstrapJobHandler',
    'signup queues canonical DNS job' => "'custom_domain.verify_dns' => ['hostname' => \$domain]",
];

foreach ($checks as $label => $needle) {
    $haystack = str_contains($label, 'signup') ? $signup : $runOnce;
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$label}: {$needle}\n");
        exit(1);
    }
}

foreach (['markTenantActive', 'markPlatformSubdomainActive', 'End of file.'] as $needle) {
    if (!str_contains($bootstrap, $needle)) {
        fwrite(STDERR, "Missing bootstrap handler fragment: {$needle}\n");
        exit(1);
    }
}

echo "Tenant background job handlers are registered.\n";

// End of file.
