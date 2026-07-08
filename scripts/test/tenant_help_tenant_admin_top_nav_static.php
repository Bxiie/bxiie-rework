<?php

declare(strict_types=1);

/**
 * Static regression test for tenant help-page admin navigation.
 *
 * Tenant admins reading help on their own site should have a top-nav Admin
 * link back to their site admin. The artist-facing copy should not explain
 * platform administration while the user is trying to edit their site.
 */

$root = dirname(__DIR__, 2);
$controller = $root . '/app/Http/Controllers/Platform/HelpController.php';
$source = file_get_contents($controller);

if ($source === false) {
    fwrite(STDERR, "[FAIL] Could not read HelpController.php\n");
    exit(1);
}

$required = [
    'tenantHelpAdminTopLink($currentUser, $canonicalNav)' => 'layout injects tenant admin top link',
    'private function tenantHelpAdminTopLink(?array $currentUser, string $canonicalNav): string' => 'tenant admin top-link helper exists',
    "str_replace('</nav>', \$tenantHelpAdminTopLink . '</nav>', \$canonicalNav)" => 'admin link is inserted inside canonical top nav',
    'href="/admin">Admin</a>' => 'tenant admin link points to local admin',
    "'artsfol.io', 'www.artsfol.io'" => 'platform hosts are excluded from tenant admin link',
    "str_contains(\$canonicalNav, 'platform-admin-top-link')" => 'duplicate Admin link guard exists',
];

$missing = [];
foreach ($required as $needle => $label) {
    if (!str_contains($source, $needle)) {
        $missing[] = $label . ': ' . $needle;
    }
}

$forbidden = [
    'Platform-wide administration is not used for normal site editing.',
];

foreach ($forbidden as $needle) {
    if (str_contains($source, $needle)) {
        $missing[] = 'forbidden help copy remains: ' . $needle;
    }
}

if ($missing !== []) {
    fwrite(STDERR, "[FAIL] Tenant help admin top-nav static check failed:\n");
    foreach ($missing as $message) {
        fwrite(STDERR, "[FAIL]  - {$message}\n");
    }
    exit(1);
}

fwrite(STDOUT, "[PASS] Tenant help admin top-nav static check passed.\n");

// End of file.
