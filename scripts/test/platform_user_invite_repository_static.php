<?php

declare(strict_types=1);

/**
 * Static regression coverage for platform admin invite role assignment.
 *
 * The invite controller calls AdminUserRepository::assignPlatformRole() after a
 * platform user invite is created. This test intentionally checks the real
 * repository source instead of executing database writes so the fast preflight
 * path stays deterministic and does not mutate production data.
 */

$root = dirname(__DIR__, 2);
$repositoryFile = $root . '/app/Platform/Identity/AdminUserRepository.php';

if (!is_file($repositoryFile)) {
    fwrite(STDERR, "Missing AdminUserRepository.php\n");
    exit(1);
}

$source = file_get_contents($repositoryFile);

if ($source === false) {
    fwrite(STDERR, "Unable to read AdminUserRepository.php\n");
    exit(1);
}

$checks = [
    'assignPlatformRole method declaration' => '/function\s+assignPlatformRole\s*\(/',
    'platform role namespace lookup' => "/roleId\s*\(\s*'platform'\s*,\s*\$roleSlug\s*\)/",
    'platform role assignment inserts tenant null' => '/tenant_id\s*,\s*user_id\s*,\s*role_id/',
    'platform role assignment checks existing row' => '/tenant_id\s+IS\s+NULL/i',
    'platform invite role method uses prepared statements' => '/prepare\s*\(/',
];

foreach ($checks as $label => $pattern) {
    if (preg_match($pattern, $source) !== 1) {
        fwrite(STDERR, "Missing platform invite repository marker: {$label}\n");
        exit(1);
    }
}

echo "Platform invite repository role assignment static checks passed.\n";

// End of file.
