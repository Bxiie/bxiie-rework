<?php

declare(strict_types=1);

/**
 * Static regression coverage for platform admin invite role assignment.
 *
 * This test checks the source contract without executing database writes. It
 * deliberately uses literal substring checks instead of PHP double-quoted regex
 * strings because `$roleSlug` in regex text is easy to accidentally convert into
 * an end-of-string anchor.
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

$requiredNeedles = [
    'assignPlatformRole method declaration' => 'function assignPlatformRole(int $userId, string $roleSlug): void',
    'platform role namespace lookup' => "roleId('platform', \$roleSlug)",
    'platform role assignment checks existing row' => 'tenant_id IS NULL',
    'platform role assignment inserts role assignments' => 'INSERT INTO role_assignments',
    'platform invite calls assignPlatformRole' => "\$this->assignPlatformRole(\$userId, 'admin')",
    'platform invite uses prepared statements' => '$this->pdo->prepare(',
];

foreach ($requiredNeedles as $label => $needle) {
    if (strpos($source, $needle) === false) {
        fwrite(STDERR, "Missing platform invite repository marker: {$label}\n");
        exit(1);
    }
}

$methodDeclarationCount = substr_count($source, 'function assignPlatformRole(');
if ($methodDeclarationCount !== 1) {
    fwrite(STDERR, "Expected exactly one assignPlatformRole() declaration; found {$methodDeclarationCount}\n");
    exit(1);
}

echo "Platform invite repository role assignment static checks passed.\n";

// End of file.
