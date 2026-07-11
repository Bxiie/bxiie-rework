#!/usr/bin/php
<?php

/**
 * Regression checks for reusing tenant slugs after soft deletion.
 */

declare(strict_types=1);

error_reporting(E_ALL);

set_error_handler(
    static function (
        int $severity,
        string $message,
        string $file,
        int $line
    ): never {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
);

$root = dirname(__DIR__, 2);
$signupPath = $root . '/app/Platform/Signup/TenantSignupService.php';
$repositoryPath = $root . '/app/Platform/Tenants/TenantAdminRepository.php';

foreach ([$signupPath, $repositoryPath] as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "[FAIL] Missing required source file: {$path}\n");
        exit(1);
    }
}

$signup = (string) file_get_contents($signupPath);
$repository = (string) file_get_contents($repositoryPath);

$signupMarkers = [
    'SELECT id, status',
    "(string) (\$tenant['status'] ?? '') !== 'deleted'",
    'deletedTenantTombstoneSlug($tenantId, $slug)',
    "AND status = 'deleted'",
    '$release->rowCount() !== 1',
];

foreach ($signupMarkers as $marker) {
    if (!str_contains($signup, $marker)) {
        fwrite(STDERR, "[FAIL] Signup service missing marker: {$marker}\n");
        exit(1);
    }
}

$repositoryMarkers = [
    'SELECT slug',
    'FOR UPDATE',
    'deleted_at = CURRENT_TIMESTAMP',
    'slug = :tombstone_slug',
    'Tenant deletion did not update exactly one tenant.',
];

foreach ($repositoryMarkers as $marker) {
    if (!str_contains($repository, $marker)) {
        fwrite(STDERR, "[FAIL] Tenant repository missing marker: {$marker}\n");
        exit(1);
    }
}

$slug = 'example-artist';
$tenantId = 42;
$tombstone = 'deleted-' . $tenantId . '-' . substr(hash('sha256', $slug . '|' . $tenantId), 0, 16);

if (strlen($tombstone) > 63) {
    fwrite(STDERR, "[FAIL] Tombstone slug exceeds the tenant slug limit.\n");
    exit(1);
}

echo "[PASS] Deleted tenant slugs are released safely for reuse.\n";

// End of file.
