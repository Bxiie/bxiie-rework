<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/app/Http/Controllers/Platform/Admin/OperationsController.php';
$controller = file_get_contents($controllerPath);

$failures = [];

if ($controller === false) {
    $failures[] = 'Unable to read OperationsController.php.';
} else {
    foreach ([
        'private function displayUtcTime(string $raw): string',
        'private function utcTimestamp(string $raw): ?int',
        'private function displayTimezone(): string',
        "new \\DateTimeZone('UTC')",
        '$this->displayUtcTime((string) $run[\'created_at\'])',
        '$this->displayUtcTime((string) $metric[\'created_at\'])',
        '$this->displayUtcTime((string) $point[\'created_at\'])',
        '$this->displayTimezone()',
    ] as $needle) {
        if (!str_contains($controller, $needle)) {
            $failures[] = "OperationsController.php missing {$needle}";
        }
    }

    if (substr_count(
        $controller,
        '$this->displayUtcTime((string) $run[\'created_at\'])'
    ) !== 2) {
        $failures[] = 'Both run timestamp displays are not converted.';
    }

    foreach ([
        "AdminLayout::escape((string) \$run['created_at'])",
        "AdminLayout::escape((string) \$metric['created_at'])",
        "AdminLayout::escape((string) \$point['created_at'])",
        ' (UTC).</p>',
    ] as $forbidden) {
        if (str_contains($controller, $forbidden)) {
            $failures[] = "OperationsController.php still contains {$forbidden}";
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Platform operations time-zone static checks failed:\n - "
        . implode("\n - ", $failures)
        . "\n");
    exit(1);
}

fwrite(STDOUT, "Platform operations time-zone static checks passed.\n");

/* End of file. */