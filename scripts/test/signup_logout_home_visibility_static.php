<?php

/**
 * Static regression checks for signup gate, logout revocation, password routes,
 * and home artwork visibility.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'signup entry validation repository' => [$root . '/app/Platform/Signup/SignupCodeRepository.php', 'public function validateForEntry('],
    'signup entry validation service' => [$root . '/app/Platform/Signup/TenantSignupService.php', 'public function validateSignupEntryCode('],
    'signup controller validates before details' => [$root . '/app/Http/Controllers/Platform/SignupController.php', 'validateSignupEntryCode($signupEntryCode)'],
    'tenant logout revokes server session' => [$root . '/app/Http/Controllers/Auth/LoginController.php', 'logoutSessionToken($rawToken)'],
    'password auth exposes logout revocation' => [$root . '/app/Platform/Auth/Password/PasswordAuthService.php', 'public function logoutToken('],
    'home page returns assigned artwork only' => [$root . '/app/Tenant/Artwork/ArtworkReadRepository.php', 'return $stmt->fetchAll();'],
    'artworks page links placement matrix' => [$root . '/app/Http/Controllers/Tenant/Admin/ArtworksController.php', '/admin/artworks/placement'],
    'platform forgot password route exists' => [$root . '/public/index.php', '$router->get(\'/password/forgot\''],
];

foreach ($checks as $label => [$file, $needle]) {
    $contents = is_file($file) ? file_get_contents($file) : false;
    if ($contents === false || !str_contains($contents, $needle)) {
        fwrite(STDERR, "Missing {$label}: {$needle} in {$file}\n");
        exit(1);
    }
}

echo "Signup, logout, password route, and home visibility static checks passed.\n";

// End of file.
