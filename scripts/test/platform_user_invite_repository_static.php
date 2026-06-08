<?php

declare(strict_types=1);

/**
 * Static regression test for platform-admin invite repository role assignment.
 */

$root = dirname(__DIR__, 2);
$repository = file_get_contents($root . '/app/Platform/Identity/AdminUserRepository.php');

$required = [
    'function invitePlatformUser(',
    'private function assignPlatformRole(',
    "roleId('platform', $roleSlug)",
    'tenant_id IS NULL',
    'VALUES (:role_id, :user_id, NULL, CURRENT_TIMESTAMP)',
];

foreach ($required as $needle) {
    if (!str_contains($repository, $needle)) {
        fwrite(STDERR, "Missing platform invite repository marker: {$needle}
");
        exit(1);
    }
}

if (preg_match('/assignPlatformRole[\s\S]*INSERT\s+IGNORE/i', $repository) === 1) {
    fwrite(STDERR, "assignPlatformRole must not rely on INSERT IGNORE because tenant_id is NULL for platform roles.
");
    exit(1);
}

// End of file.
