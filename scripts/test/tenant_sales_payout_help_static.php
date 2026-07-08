<?php

/**
 * Static regression check for artist-facing payout help and Settings copy.
 */

$root = dirname(__DIR__, 2);
$help = file_get_contents($root . '/app/Http/Controllers/Platform/HelpController.php');
$settings = file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/SettingsController.php');

$failures = [];

$helpMarkers = [
    '<h2>How you get paid</h2>',
    'ArtsFolio uses Stripe Connect for artwork payments.',
    'Click <strong>Settings</strong> in the sidebar',
    'Click <strong>Sales</strong> in the sidebar',
    'Stripe is connected before you take live sales.',
];

foreach ($helpMarkers as $marker) {
    if (!str_contains($help, $marker)) {
        $failures[] = 'Missing sales help marker: ' . $marker;
    }
}

$settingsMarkers = [
    'How you get paid',
    'Open Stripe payout settings',
    'Stripe connected account ID <span class="admin-muted">manual setup</span>',
    'Paste your <code>acct_...</code> ID here after your Stripe account is connected.',
];

foreach ($settingsMarkers as $marker) {
    if (!str_contains($settings, $marker)) {
        $failures[] = 'Missing settings payout marker: ' . $marker;
    }
}

$badHelpCopy = [
    'Open /admin/settings',
    'Go to /admin/sales',
    'the tenant setup allow',
    'tenant-scoped cart',
];

foreach ($badHelpCopy as $marker) {
    if (str_contains($help, $marker)) {
        $failures[] = 'Found path-heavy or tenant-heavy sales copy: ' . $marker;
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Tenant sales payout help static check failed:
");
    foreach ($failures as $failure) {
        fwrite(STDERR, '[FAIL]  - ' . $failure . "
");
    }
    exit(1);
}

echo "[PASS] Tenant sales payout help static check passed.
";

// End of file.
