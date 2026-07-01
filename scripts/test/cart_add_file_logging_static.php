<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];

$controller = file_get_contents($root . '/app/Http/Controllers/Tenant/SalesController.php') ?: '';
foreach ([
    'private function logCartAddFailure',
    '[ArtsFolio cart/add]',
    'storage/logs',
    'cart_add.log',
    '/tmp/artsfolio_cart_add.log',
    'file_put_contents($logPath',
    'error_log($line)',
] as $needle) {
    if (!str_contains($controller, $needle)) {
        $failures[] = "SalesController missing {$needle}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Cart-add file logging static checks failed:
");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}
");
    }
    exit(1);
}

echo "Cart-add file logging static checks passed.
";

// End of file.
