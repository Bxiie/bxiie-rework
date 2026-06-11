<?php

/**
 * Compatibility wrapper for the old v49 pricing-inline check.
 *
 * v50 replaced the earlier inline-repair marker with the final pricing polish
 * block. Keep this file passing while preflight deployments still reference it.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$file = $root . '/app/Http/Controllers/Platform/PricingController.php';
$content = (string) file_get_contents($file);

foreach ([
    'Pricing page final polish: Studio contrast',
    "['professional', 'Choose Professional', '/signup?plan=professional']",
    "['collective', 'Choose Collective', '/signup?plan=collective']",
    "['studio', 'Choose Studio', '/signup?plan=studio']",
    "adminHeader.textContent = 'Admin users';",
] as $needle) {
    if (!str_contains($content, $needle)) {
        fwrite(STDERR, "FAILED: pricing final polish compatibility check missing {$needle}\n");
        exit(1);
    }
}

echo "Pricing inline/final polish compatibility checks passed.\n";

// End of file.
