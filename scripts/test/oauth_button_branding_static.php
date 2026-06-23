<?php

declare(strict_types=1);

/**
 * Regression checks for provider-branded OAuth login buttons.
 */

$projectRoot = dirname(__DIR__, 2);
$authPath = $projectRoot . '/app/Http/View/AuthPage.php';
$cssPath = $projectRoot . '/public/assets/auth.css';

$auth = file_get_contents($authPath);
$css = file_get_contents($cssPath);
if ($auth === false || $css === false) {
    fwrite(STDERR, "Unable to read OAuth branding source files.\n");
    exit(1);
}

$checks = [
    'Google provider button class' => 'oauth-button-google',
    'Facebook provider button class' => 'oauth-button-facebook',
    'Google provider icon class' => 'oauth-provider-icon-google',
    'Facebook provider icon class' => 'oauth-provider-icon-facebook',
    'Google login label' => 'Continue with Google',
    'Facebook login label' => 'Continue with Facebook',
];

$failures = [];
foreach ($checks as $description => $needle) {
    if (!str_contains($auth, $needle)) {
        $failures[] = "{$description} missing: {$needle}";
    }
}
foreach (['.oauth-button-google', '.oauth-button-facebook', '.oauth-provider-icon svg'] as $needle) {
    if (!str_contains($css, $needle)) {
        $failures[] = "OAuth CSS missing: {$needle}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "OAuth button branding static check failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "OAuth button branding static checks passed.\n";

// End of file.
