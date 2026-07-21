<?php

declare(strict_types=1);

/**
 * Static regression checks for auth/email/signup/artwork placement repair.
 */

$root = dirname(__DIR__, 2);
$checks = [
    'password reset template token' => [$root . '/template/email/auth/password-reset-request.md', '{{reset_url}}'],
    'password reset template key' => [$root . '/app/Platform/Email/LifecycleEmailService.php', "templateKey: 'auth.password_reset_request'"],
    'password reset subject' => [$root . '/app/Platform/Email/LifecycleEmailService.php', "subject: 'Reset your ArtsFolio password'"],
    'public forgot route' => [$root . '/app/Http/Routes/platform.php', "\$router->get('/password/forgot'"],
    'public reset route' => [$root . '/app/Http/Routes/platform.php', "\$router->get('/password/reset'"],
    'artwork placement route' => [$root . '/app/Http/Routes/tenant.php', '/admin/artworks/placement'],
    'section order route' => [$root . '/app/Http/Routes/tenant.php', '/admin/portfolio-sections/order'],
    'artwork placement controller' => [$root . '/app/Http/Controllers/Tenant/Admin/ArtworkPlacementController.php', 'Artwork Placement Matrix'],
    'homepage assignment migration' => [$root . '/database/migrations/0033_homepage_artwork_assignments.sql', 'homepage_artwork_assignments'],
    'signup passcode preprompt' => [$root . '/app/Http/Controllers/Platform/SignupController.php', 'ARTSFOLIO_SIGNUP_PASSCODE_PREPROMPT'],
    'signup required helper' => [$root . '/app/Platform/Signup/TenantSignupService.php', 'requiresSignupCode'],
    'email logo correct path' => [$root . '/app/Platform/Email/BrandedEmail.php', '/assets/logo_2.png'],
    'smtp multipart html' => [$root . '/app/Platform/Email/SmtpEmailSender.php', 'multipart/alternative'],
];
foreach ($checks as $label => [$file, $needle]) {
    if (!is_file($file)) { fwrite(STDERR, "Missing file for {$label}: {$file}\n"); exit(1); }
    $source = file_get_contents($file);
    if ($source === false || strpos($source, $needle) === false) { fwrite(STDERR, "Missing marker for {$label}: {$needle}\n"); exit(1); }
}
echo "Admin/auth/content static checks passed.\n";

// End of file.
