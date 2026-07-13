<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$routes = (string) file_get_contents($root . '/app/Http/Routes/platform.php');
$failures = [];

$complete = 'new PlatformAdminOperationsController('
    . 'new RequirePlatformRole(new MembershipRepository($pdo)), '
    . 'new OperationsMonitorRepository($pdo), '
    . 'new CsrfTokenService(), '
    . 'new AuditLogRepository($pdo), '
    . 'new OperationsTaskLauncher()'
    . ')';

foreach ([
    "/platform/admin/operations/metrics",
    "/platform/admin/operations/runs/{id}",
] as $route) {
    $pos = strpos($routes, $route);
    if ($pos === false) {
        $failures[] = "Missing route: {$route}";
        continue;
    }

    if (!str_contains(substr($routes, $pos, 700), $complete)) {
        $failures[] = "Incomplete OperationsController constructor: {$route}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Platform Operations route constructor check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Platform Operations routes use the complete controller constructor.\n";

// End of file.
