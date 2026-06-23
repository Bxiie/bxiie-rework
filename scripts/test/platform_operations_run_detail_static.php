<?php
declare(strict_types=1);

/**
 * Regression checks for operation-run detail date-range links.
 */

$projectRoot = dirname(__DIR__, 2);
$controllerPath = $projectRoot . '/app/Http/Controllers/Platform/Admin/OperationsController.php';

$controllerContents = file_get_contents($controllerPath);

if ($controllerContents === false) {
    fwrite(STDERR, "Platform operations run-detail static check failed: unable to read controller.\n");
    exit(1);
}

$failures = [];

foreach ([
    "\$runTimestamp = strtotime((string) (\$run['created_at'] ?? '')) ?: time();",
    "\$defaultEnd = date('Y-m-d', \$runTimestamp);",
    "\$defaultStart = date('Y-m-d', strtotime('-7 days', \$runTimestamp));",
    "\$start = trim((string) (\$_GET['start'] ?? \$defaultStart));",
    "\$end = trim((string) (\$_GET['end'] ?? \$defaultEnd));",
    "'&start=' . rawurlencode(\$start) . '&end=' . rawurlencode(\$end)",
] as $requiredText) {
    if (!str_contains($controllerContents, $requiredText)) {
        $failures[] = "OperationsController.php missing: {$requiredText}";
    }
}

$runMethodPosition = strpos($controllerContents, 'public function run(');
$startDefinitionPosition = strpos($controllerContents, "\$start = trim(", $runMethodPosition ?: 0);
$metricLinkPosition = strpos($controllerContents, "rawurlencode(\$start)", $runMethodPosition ?: 0);

if (
    $runMethodPosition === false
    || $startDefinitionPosition === false
    || $metricLinkPosition === false
    || $startDefinitionPosition > $metricLinkPosition
) {
    $failures[] = 'The run-detail date range must be defined before metric links are built.';
}

if ($failures !== []) {
    fwrite(
        STDERR,
        "Platform operations run-detail static check failed:\n - "
        . implode("\n - ", $failures)
        . "\n"
    );
    exit(1);
}

fwrite(STDOUT, "Platform operations run-detail static checks passed.\n");

/* End of file. */