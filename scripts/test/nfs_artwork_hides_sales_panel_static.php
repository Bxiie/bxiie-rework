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

    $methodName = null;
    foreach (['function artworkSalesPanel', 'function salesPanel'] as $candidate) {
        if (str_contains($home, $candidate)) {
            $methodName = $candidate;
            break;
        }
    }
    if ($methodName === null) {
        $failures[] = 'HomeController missing artworkSalesPanel sales panel method';
    }

    foreach ([
        <<<'NEEDLE'
(string) ($artwork['sale_status'] ?? '') !== 'for_sale'
NEEDLE,
        <<<'NEEDLE'
return '';
NEEDLE,
        <<<'NEEDLE'
NFS artwork never renders the Sales panel
NEEDLE,
    ] as $needle) {
        if (!str_contains($home, $needle)) {
            $failures[] = 'HomeController missing ' . str_replace("\n", '\\n', $needle);
        }
    }

    if ($methodName !== null) {
        $methodPosition = strpos($home, $methodName);
        $saleStatusPosition = strpos($home, "(string) (\$artwork['sale_status'] ?? '') !== 'for_sale'", $methodPosition ?: 0);
        $salesHeaderPosition = strpos($home, '<h2>Sales</h2>', $methodPosition ?: 0);
        $directSalesCopyPosition = strpos($home, 'Sales are handled directly by the artist', $methodPosition ?: 0);

        if ($saleStatusPosition === false) {
            $failures[] = 'HomeController sale-status guard must be inside the sales panel method.';
        }
        if ($saleStatusPosition !== false && $salesHeaderPosition !== false && $saleStatusPosition > $salesHeaderPosition) {
            $failures[] = 'HomeController sale-status guard appears after the Sales header render.';
        }
        if ($saleStatusPosition !== false && $directSalesCopyPosition !== false && $saleStatusPosition > $directSalesCopyPosition) {
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
