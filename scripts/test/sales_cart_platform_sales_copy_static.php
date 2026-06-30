<?php
/**
 * Static regression check for public sales copy around platform checkout.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$home = $root . '/app/Http/Controllers/Tenant/HomeController.php';

$failures = [];
if (!is_file($home)) {
    $failures[] = 'Missing app/Http/Controllers/Tenant/HomeController.php';
} else {
    $contents = file_get_contents($home) ?: '';
    $needles = [
        'platformCheckoutConfigured($tenant, $artwork, $config)',
        'private function platformCheckoutConfigured(TenantContext $tenant, array $artwork, ?array $config): bool',
        '$platformCheckoutConfigured = $this->platformCheckoutConfigured',
        '$notesHtml = (!$platformCheckoutConfigured && $salesNotes !==',
        'Direct-artist sales notes are useful for inquiry-only artworks',
        'return $this->tenantSalesEnabled($tenant);',
    ];

    foreach ($needles as $needle) {
        if (!str_contains($contents, $needle)) {
            $failures[] = "Missing {$needle} in app/Http/Controllers/Tenant/HomeController.php";
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Platform sales copy static checks failed:
 - " . implode("
 - ", $failures) . "
");
    exit(1);
}

echo "Platform sales copy static checks passed.
";

// End of file.
