<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$path = $root . '/scripts/test/preflight.sh';
$contents = file_get_contents($path);

if ($contents === false) {
    fwrite(STDERR, "Preflight status-output static check failed: unable to read preflight.sh.\n");
    exit(1);
}

$failures = [];

foreach ([
    'prefix_lines "[PASS]"',
    'prefix_lines "[FAIL]"',
    "printf '[PASS] Preflight completed successfully.",
    'printf "[FAIL] Preflight stopped at line',
    'run_command()',
] as $needle) {
    if (!str_contains($contents, $needle)) {
        $failures[] = "preflight.sh missing {$needle}";
    }
}

if (str_contains($contents, "\necho \"Preflight passed.\"")) {
    $failures[] = 'preflight.sh still contains the old premature success line.';
}

if (preg_match('/^run_php "\$file"/m', $contents) === 1) {
    $failures[] = 'preflight.sh contains a leaked top-level $file reference.';
}

if ($failures !== []) {
    fwrite(STDERR, "Preflight status-output static check failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "Preflight status-output static checks passed.\n");

/* End of file. */