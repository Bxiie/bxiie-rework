<?php

/**
 * Static regression guard for tenant session bridge domain-column drift.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$file = $root . '/app/Platform/Auth/Session/SessionBridgeRepository.php';
$source = file_get_contents($file);

if ($source === false) {
    fwrite(STDERR, "Unable to read SessionBridgeRepository.php\n");
    exit(1);
}

$badNeedles = [
    'LOWER(domain)',
    'LOWER(d.domain)',
    ' d.domain',
    'SELECT t.slug, d.domain',
];

foreach ($badNeedles as $needle) {
    if (str_contains($source, $needle)) {
        fwrite(STDERR, "Session bridge still references missing tenant_domains.domain via {$needle}\n");
        exit(1);
    }
}

$goodNeedles = [
    'LOWER(hostname)',
];

foreach ($goodNeedles as $needle) {
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, "Session bridge does not contain expected hostname lookup {$needle}\n");
        exit(1);
    }
}

echo "Tenant session bridge hostname static check passed.\n";

// End of file.
