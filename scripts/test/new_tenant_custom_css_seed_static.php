<?php

declare(strict_types=1);

/**
 * Regression checks for documented Custom CSS on newly created tenants.
 */

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents(
    $root . '/app/Platform/Signup/TenantSignupService.php'
);
$failures = [];

$required = [
    "setting_key = 'custom_css'",
    "if (\$stmt->fetchColumn() !== false) {\n            return;",
    "\$path = \$root . '/public/assets/site.css';",
    'ArtsFolio Custom CSS',
    'HOW THE CASCADE WORKS',
    'SAFE WORKFLOW',
    'IMPORTANT CSS VARIABLES',
    'COMMON SELECTORS',
    'RESPONSIVE RULES',
    'Tenant additions',
    'copied from public/assets/site.css',
    'ArtsFolio does',
    'not automatically replace this field',
    '/* End of file. */',
];

foreach ($required as $marker) {
    if (!str_contains($service, $marker)) {
        $failures[] = "TenantSignupService missing marker: {$marker}";
    }
}

$forbidden = [
    "public/assets/platform.css",
    "\$this->updateKnown('tenant_settings'",
];

foreach ($forbidden as $marker) {
    if (str_contains($service, $marker)) {
        $failures[] = "TenantSignupService contains forbidden CSS seed behavior: {$marker}";
    }
}

$siteCss = (string) file_get_contents($root . '/public/assets/site.css');
if ($siteCss === '') {
    $failures[] = 'public/assets/site.css is empty or unreadable.';
} elseif (!str_starts_with(ltrim($siteCss), '/*')) {
    $failures[] = 'public/assets/site.css must begin with a human-readable comment.';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] New tenant Custom CSS seed check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] New tenants receive documented current Custom CSS.\n";

// End of file.
