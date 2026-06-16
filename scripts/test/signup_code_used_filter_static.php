<?php

declare(strict_types=1);

/**
 * Static regression checks for platform signup-code used/revoked list filters.
 */

$controllerPath = __DIR__ . '/../../app/Http/Controllers/Platform/Admin/SignupCodesController.php';
$repositoryPath = __DIR__ . '/../../app/Platform/Signup/SignupCodeRepository.php';

$controller = file_get_contents($controllerPath);
$repository = file_get_contents($repositoryPath);

if ($controller === false) {
    fwrite(STDERR, "Could not read SignupCodesController.php\n");
    exit(1);
}

if ($repository === false) {
    fwrite(STDERR, "Could not read SignupCodeRepository.php\n");
    exit(1);
}

$checks = [
    'filter form submit marker' => 'signup_code_filter_saved',
    'unchecked show used defaults false' => '$_GET[\'show_used\'] ?? \'0\'',
    'unchecked show revoked defaults false' => '$_GET[\'show_revoked\'] ?? \'0\'',
    'filter submit controls cookie write' => '$filterSubmitted',
    'show used cookie name' => 'artsfolio_signup_codes_show_used',
    'show revoked cookie name' => 'artsfolio_signup_codes_show_revoked',
    'show used label' => 'Show used codes',
    'show revoked label' => 'Show revoked codes',
];

foreach ($checks as $label => $needle) {
    if (!str_contains($controller, $needle)) {
        fwrite(STDERR, "Missing signup code filter controller check: {$label}\n");
        exit(1);
    }
}

$repositoryChecks = [
    'list recent include used parameter' => 'bool $includeUsed = false',
    'list recent include revoked parameter' => 'bool $includeRevoked = false',
    'used status is terminal' => "'used'",
    'revoked status is filterable' => "'revoked'",
];

foreach ($repositoryChecks as $label => $needle) {
    if (!str_contains($repository, $needle)) {
        fwrite(STDERR, "Missing signup code filter repository check: {$label}\n");
        exit(1);
    }
}

echo "Signup code used/revoked filter static checks passed.\n";

// End of file.
