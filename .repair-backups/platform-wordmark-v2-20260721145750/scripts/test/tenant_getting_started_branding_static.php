<?php

declare(strict_types=1);

/**
 * Static regression check for tenant getting-started platform branding.
 *
 * The newly created tenant onboarding page is a tenant-admin page, but it is
 * also part of the platform signup handoff. It must show the ArtsFolio platform
 * logo and keep its current copy in the controller until a CMS-backed editor is
 * introduced.
 */

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/app/Http/Controllers/Tenant/Admin/GettingStartedController.php';

$source = file_get_contents($controllerPath);

if ($source === false) {
    fwrite(STDERR, "Could not read {$controllerPath}\n");
    exit(1);
}

$required = [
    '/assets/logo_2.png' => 'platform logo asset',
    'platform-brand' => 'platform branding header class',
    'Tenant setup powered by ArtsFolio' => 'platform handoff copy',
    'aria-label="ArtsFolio platform branding"' => 'accessible platform branding label',
];

foreach ($required as $needle => $description) {
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, "Failed tenant getting-started branding static check: missing {$description}.\n");
        exit(1);
    }
}

echo "Tenant getting-started branding static checks passed.\n";

// End of file.
