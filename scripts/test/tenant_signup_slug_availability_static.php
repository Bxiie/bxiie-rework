<?php

declare(strict_types=1);

/**
 * Guards the public tenant signup slug-availability validation against the
 * missing-method regression that caused HTTP 422 responses during signup.
 */

$servicePath = __DIR__ . '/../../app/Platform/Signup/TenantSignupService.php';
$contents = file_get_contents($servicePath);

if ($contents === false) {
    fwrite(STDERR, "Could not read {$servicePath}\n");
    exit(1);
}

$required = [
    'Signup calls slug availability validation' => '$this->ensureTenantSlugAvailable($slug);',
    'Slug availability method exists' => 'private function ensureTenantSlugAvailable(string $slug): void',
    'Slug lookup is parameterized' => "SELECT id, status",
    'Slug collision has a user-facing message' => 'That site address is already in use. Please choose another.',
    'Missing tenant storage is rejected' => 'Tenant storage is not available.',
    'Missing slug schema is rejected' => 'Tenant storage does not contain a slug column.',
];

foreach ($required as $label => $needle) {
    if (!str_contains($contents, $needle)) {
        fwrite(STDERR, "[FAIL] {$label}: missing {$needle}\n");
        exit(1);
    }
}

$methodCount = substr_count($contents, 'private function ensureTenantSlugAvailable(string $slug): void');
if ($methodCount !== 1) {
    fwrite(STDERR, "[FAIL] Expected one ensureTenantSlugAvailable() method; found {$methodCount}.\n");
    exit(1);
}

echo "[PASS] Tenant signup slug availability static check passed.\n";

// End of file.
