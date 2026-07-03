<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];
$homePath = $root . '/app/Http/Controllers/Tenant/HomeController.php';
$home = file_get_contents($homePath) ?: '';

$badPatterns = [
    '/\bpublic\s+public\s+function\b/',
    '/\bprivate\s+private\s+function\b/',
    '/\bprotected\s+protected\s+function\b/',
    '/\bpublic\s+private\s+function\b/',
    '/\bprivate\s+public\s+function\b/',
    '/\bprivate\s*\/\*.*?\*\/\s*private\s+function\b/s',
    '/\bpublic\s*\/\*.*?\*\/\s*public\s+function\b/s',
    '/\bprotected\s*\/\*.*?\*\/\s*protected\s+function\b/s',
];
foreach ($badPatterns as $pattern) {
    if (preg_match($pattern, $home)) {
        $failures[] = "HomeController has duplicate or stray access modifier matching {$pattern}";
    }
}

foreach (['collapsibleCurationControls', 'tenant-curation-controls-toggle'] as $needle) {
    if (!str_contains($home, $needle)) {
        $failures[] = "HomeController missing {$needle}";
    }
}

$cmd = 'php -l ' . escapeshellarg($homePath) . ' 2>&1';
$output = shell_exec($cmd) ?? '';
if (!str_contains($output, 'No syntax errors detected')) {
    $failures[] = 'HomeController does not pass php -l: ' . trim(str_replace("\n", ' ', $output));
}

if ($failures !== []) {
    fwrite(STDERR, "HomeController access modifier static checks failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "HomeController access modifier static checks passed.\n";

// End of file.
