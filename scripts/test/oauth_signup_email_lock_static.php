#!/usr/bin/php
<?php

/**
 * Static regression checks for OAuth tenant signup email locking.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$signupPath = $root . '/app/Http/Controllers/Platform/SignupController.php';
$authCssPath = $root . '/public/assets/auth.css';

$signup = file_get_contents($signupPath);
$authCss = file_get_contents($authCssPath);

if ($signup === false) {
    fwrite(STDERR, "Could not read {$signupPath}\n");
    exit(1);
}

if ($authCss === false) {
    fwrite(STDERR, "Could not read {$authCssPath}\n");
    exit(1);
}

$checks = [
    'Signup form renders OAuth provider email as readonly' => [$signup, 'readonly aria-readonly="true" class="readonly-input"'],
    'Signup form explains provider email cannot be changed' => [$signup, 'cannot be changed during OAuth signup'],
    'Signup form preserves signup code through Google OAuth return_to' => [$signup, '$googleSignupUrl = \'/auth/google?return_to=\' . rawurlencode($oauthReturnTo);'],
    'Signup form preserves signup code through Facebook OAuth return_to' => [$signup, '$facebookSignupUrl = \'/auth/facebook?return_to=\' . rawurlencode($oauthReturnTo);'],
    'Signup submit resolves OAuth email from session' => [$signup, '$adminEmail = $oauthProfile !== null ? $oauthEmail'],
    'Signup submit validates OAuth provider email' => [$signup, 'The OAuth provider did not provide a valid email address.'],
    'Signup submit does not trust posted OAuth email' => [$signup, 'adminEmail: $adminEmail'],
    'Readonly email field has visible styling' => [$authCss, 'input.readonly-input'],
];

foreach ($checks as $label => [$contents, $needle]) {
    if (!str_contains($contents, $needle)) {
        fwrite(STDERR, "Failed OAuth signup email lock static check: {$label}\n");
        exit(1);
    }
}

if (str_contains($signup, "adminEmail: (string) (\$_POST['email'] ?? '')")) {
    fwrite(STDERR, "Signup submit still trusts posted email directly.\n");
    exit(1);
}

echo "OAuth signup email lock static checks passed.\n";

// End of file.
