<?php

declare(strict_types=1);

/**
 * Regression checks for the dedicated Tenant Admin Onboarding tab.
 */

$root = dirname(__DIR__, 2);
$failures = [];

$sources = [
    'page' => (string) file_get_contents(
        $root . '/app/Http/Controllers/Tenant/Admin/OnboardingPageController.php'
    ),
    'reset' => (string) file_get_contents(
        $root . '/app/Http/Controllers/Tenant/Admin/OnboardingController.php'
    ),
    'dashboard' => (string) file_get_contents(
        $root . '/app/Http/Controllers/Tenant/Admin/DashboardController.php'
    ),
    'nav' => (string) file_get_contents($root . '/app/Http/View/TenantAdminNav.php'),
    'routes' => (string) file_get_contents($root . '/app/Http/Routes/tenant.php'),
];

$required = [
    'page' => [
        'Onboarding checklist',
        'href="/admin/getting-started"',
        'Guided tour',
        'href="/help/new-admin-tour"',
        'role="switch"',
        'name="reset_onboarding_confirm"',
        'action="/admin/onboarding/reset"',
        "'onboarding'",
    ],
    'reset' => [
        "reset_onboarding_confirm",
        "/admin/onboarding?notice=onboarding-reset&onboarding_reset=1",
        "tenant.onboarding.reset",
    ],
    'nav' => [
        "'onboarding' => ['/admin/onboarding', 'Onboarding']",
    ],
    'routes' => [
        '$router->get(\'/admin/onboarding\'',
        'TenantAdminOnboardingPageController',
    ],
];

foreach ($required as $sourceName => $markers) {
    foreach ($markers as $marker) {
        if (!str_contains($sources[$sourceName], $marker)) {
            $failures[] = "{$sourceName} missing marker: {$marker}";
        }
    }
}

$dashboardForbidden = [
    '<h3>Onboarding</h3>',
    'action="/admin/onboarding/reset"',
    '$onboardingNotice',
    'onboarding_reset',
];

foreach ($dashboardForbidden as $marker) {
    if (str_contains($sources['dashboard'], $marker)) {
        $failures[] = "Dashboard still contains onboarding UI marker: {$marker}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Tenant onboarding tab static check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Tenant onboarding tab static check passed.\n";

// End of file.
