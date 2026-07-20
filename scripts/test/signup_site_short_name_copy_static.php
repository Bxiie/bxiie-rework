<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/app/Http/Controllers/Platform/SignupController.php';

if (!is_file($controllerPath)) {
    fwrite(STDERR, "[FAIL] Missing SignupController.php.\n");
    exit(1);
}

$body = (string) file_get_contents($controllerPath);
$required = [
    'Site short name',
    'Choose the short name for your ArtsFolio address.',
    'For example, “bxiie” creates bxiie.artsfol.io.',
];

foreach ($required as $marker) {
    if (!str_contains($body, $marker)) {
        fwrite(STDERR, "[FAIL] Missing signup wording marker: {$marker}\n");
        exit(1);
    }
}

if (preg_match('/>\s*Site slug\s*</i', $body) === 1) {
    fwrite(STDERR, "[FAIL] Public signup still renders Site slug.\n");
    exit(1);
}

echo "[PASS] Signup site short-name copy static check passed.\n";
