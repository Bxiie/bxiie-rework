<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$deploy = (string) file_get_contents($root . '/scripts/deploy/deploy_production.sh');
$monitor = (string) file_get_contents($root . '/scripts/ops/monitor_artsfolio.php');

$checks = [
    'deploy invokes explicit component-start notification' => '--component-started="PHP-FPM,Caddy,email worker instances,background worker instances"',
    'deploy uses notification-only exit behavior' => '--notification-only',
];
foreach ($checks as $label => $needle) {
    if (!str_contains($deploy, $needle)) {
        throw new RuntimeException($label . ': missing ' . $needle);
    }
}
foreach ([
    "'component-started:'",
    "'notification-only'",
    '$explicitStartedComponents',
    'if (isset($options[\'notification-only\']))',
] as $needle) {
    if (!str_contains($monitor, $needle)) {
        throw new RuntimeException('monitor missing deploy notification support: ' . $needle);
    }
}

echo "Deploy component-start notification static checks passed.\n";
