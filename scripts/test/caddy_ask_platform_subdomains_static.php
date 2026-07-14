<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$source = (string) file_get_contents(
    $root . '/app/Http/Controllers/Platform/CaddyAskController.php'
);
$failures = [];

foreach ([
    'isApprovedArtsFolioSubdomain($domain)',
    'private function isApprovedArtsFolioSubdomain(string $domain): bool',
    "/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.artsfol\\.io$/",
    '|| $this->isApprovedTenantDomain($domain)',
] as $marker) {
    if (!str_contains($source, $marker)) {
        $failures[] = "Missing marker: {$marker}";
    }
}

$pattern = '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.artsfol\.io$/';

foreach ([
    'training.artsfol.io',
    'foo.artsfol.io',
    'a.artsfol.io',
    'artist-123.artsfol.io',
] as $host) {
    if (preg_match($pattern, $host) !== 1) {
        $failures[] = "Expected approved host did not match: {$host}";
    }
}

foreach ([
    'artsfol.io',
    'a.b.artsfol.io',
    '-bad.artsfol.io',
    'bad-.artsfol.io',
    '*.artsfol.io',
    'example.com',
] as $host) {
    if (preg_match($pattern, $host) === 1) {
        $failures[] = "Expected rejected host matched: {$host}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Caddy ask platform-subdomain check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Caddy ask approves valid first-level ArtsFolio subdomains.\n";

// End of file.
