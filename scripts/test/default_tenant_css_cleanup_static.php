<?php

declare(strict_types=1);

/**
 * Regression checks for neutral default tenant CSS.
 */

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents(
    $root . '/app/Platform/Signup/TenantSignupService.php'
);
$siteCss = (string) file_get_contents(
    $root . '/public/assets/site.css'
);

$failures = [];

foreach ([
    "preg_replace('/\\\\bBxiie\\\\b/i', 'ArtsFolio', \$source)",
    'Keep the tenant-editable default neutral',
    'End of file\\\\.',
] as $marker) {
    if (!str_contains($service, $marker)) {
        $failures[] = "TenantSignupService missing cleanup marker: {$marker}";
    }
}

if (preg_match('/\\bbxiie\\b/i', $siteCss) === 1) {
    $failures[] = 'public/assets/site.css still contains a Bxiie reference.';
}

if (str_contains($siteCss, '/* End of file. */')) {
    $failures[] = 'public/assets/site.css still contains an End of file marker.';
}

$tenantAdditionsStart = strpos($service, '===== Tenant additions =====');
if ($tenantAdditionsStart === false) {
    $failures[] = 'Tenant additions guide is missing.';
} else {
    $tenantAdditions = substr($service, $tenantAdditionsStart, 700);
    if (str_contains($tenantAdditions, '/* End of file. */')) {
        $failures[] = 'Generated tenant additions still append an End of file marker.';
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Default tenant CSS cleanup check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Default tenant CSS is neutral and contains no End of file markers.\n";

// End of file.
