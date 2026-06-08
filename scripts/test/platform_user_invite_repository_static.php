<?php

declare(strict_types=1);

/**
 * Static regression coverage for platform-admin invite role assignment.
 *
 * The platform invite controller calls AdminUserRepository::assignPlatformRole().
 * This test keeps the repository/controller contract explicit so invite handling
 * does not regress into a missing-method fatal error.
 */

$root = dirname(__DIR__, 2);
$repositoryFile = $root . '/app/Platform/Identity/AdminUserRepository.php';

if (!is_file($repositoryFile)) {
    fwrite(STDERR, "Missing repository file: {$repositoryFile}\n");
    exit(1);
}

$source = file_get_contents($repositoryFile);

$markers = [
    'public function assignPlatformRole(' => 'assignPlatformRole method declaration',
    'roleId(\'platform\', $roleSlug)' => 'platform role lookup',
    'tenant_id IS NULL' => 'platform role assignment must not be tenant scoped',
    'INSERT INTO user_roles' => 'role assignment insert',
];

foreach ($markers as $marker => $description) {
    if (!str_contains($source, $marker)) {
        fwrite(STDERR, "Missing platform invite repository marker: {$description}\n");
        exit(1);
    }
}

echo "Platform invite repository role assignment markers are present.\n";

// End of file.
