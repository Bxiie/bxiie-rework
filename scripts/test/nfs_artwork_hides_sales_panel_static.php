<?php
/**
 * Static regression check that not-for-sale artwork detail pages do not render
 * the Sales panel or direct-artist sales copy.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];

$homePath = $root . '/app/Http/Controllers/Tenant/HomeController.php';
if (!is_file($homePath)) {
    $failures[] = 'Missing app/Http/Controllers/Tenant/HomeController.php';
} else {
    $home = file_get_contents($homePath) ?: '';
    $methodPosition = strpos($home, 'function artworkSalesPanel');
    if ($methodPosition === false) {
        $failures[] = 'HomeController missing artworkSalesPanel method';
    }

    foreach ([
        <<<'NEEDLE'
NFS artwork never renders the Sales panel
NEEDLE,
        <<<'NEEDLE'
(string) ($artwork['sale_status'] ?? '') !== 'for_sale'
NEEDLE,
        <<<'NEEDLE'
return '';
NEEDLE,
    ] as $needle) {
        if (!str_contains($home, $needle)) {
            $failures[] = 'HomeController missing ' . str_replace("\n", '\\n', $needle);
        }
    }

    if ($methodPosition !== false) {
        $guardPosition = strpos($home, "(string) (\$artwork['sale_status'] ?? '') !== 'for_sale'", $methodPosition);
        $salesHeaderPosition = strpos($home, '<h2>Sales</h2>', $methodPosition);
        $directSalesCopyPosition = strpos($home, 'Sales are handled directly by the artist', $methodPosition);

        if ($guardPosition === false) {
            $failures[] = 'HomeController sale-status guard must be inside artworkSalesPanel.';
        }
        if ($guardPosition !== false && $salesHeaderPosition !== false && $guardPosition > $salesHeaderPosition) {
            $failures[] = 'HomeController sale-status guard appears after the Sales header render.';
        }
        if ($guardPosition !== false && $directSalesCopyPosition !== false && $guardPosition > $directSalesCopyPosition) {
            $failures[] = 'HomeController sale-status guard appears after direct-sales copy render.';
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "NFS artwork hides sales panel static checks failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . "\n");
    }
    exit(1);
}

echo "NFS artwork hides sales panel static checks passed.\n";

// End of file.
