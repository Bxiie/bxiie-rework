<?php

declare(strict_types=1);

/**
 * Static regression checks for used/revoked signup-code filtering.
 */

$root = dirname(__DIR__, 2);
$files = [
    'repository' => $root . '/app/Platform/Signup/SignupCodeRepository.php',
    'controller' => $root . '/app/Http/Controllers/Platform/Admin/SignupCodesController.php',
    'migration' => $root . '/database/migrations/0036_signup_code_used_status.sql',
];

foreach ($files as $name => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$name} file: {$path}\n");
        exit(1);
    }
    $files[$name] = file_get_contents($path) ?: '';
}

$checks = [
    'repository listRecent accepts include-used flag' => str_contains($files['repository'], 'bool $includeUsed = false'),
    'repository listRecent accepts include-revoked flag' => str_contains($files['repository'], 'bool $includeRevoked = false'),
    'repository hides used status by default' => str_contains($files['repository'], '$excludedStatuses[] = \'used\''),
    'repository hides revoked status by default' => str_contains($files['repository'], '$excludedStatuses[] = \'revoked\''),
    'repository marks exhausted codes used' => str_contains($files['repository'], "THEN 'used'"),
    'controller renders show-used option' => str_contains($files['controller'], 'name="show_used"'),
    'controller renders show-revoked option' => str_contains($files['controller'], 'name="show_revoked"'),
    'controller persists used preference cookie' => str_contains($files['controller'], 'artsfolio_signup_codes_show_used'),
    'controller persists revoked preference cookie' => str_contains($files['controller'], 'artsfolio_signup_codes_show_revoked'),
    'controller calls listRecent with filter flags' => str_contains($files['controller'], 'listRecent(200, $showUsed, $showRevoked)'),
    'controller normalizes legacy redeemed to used' => str_contains($files['controller'], "if ($" . "status === 'redeemed')"),
    'migration converts redeemed rows to used' => str_contains($files['migration'], "SET status = 'used'"),
];

$failed = [];
foreach ($checks as $label => $ok) {
    if (!$ok) {
        $failed[] = $label;
    }
}

if ($failed !== []) {
    fwrite(STDERR, "Failed signup-code used/filter static checks:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo "Signup-code used status and list filters are present.\n";

// End of file.
