<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$file = $root . '/app/Http/Controllers/Platform/PricingController.php';
$content = (string) file_get_contents($file);

foreach ([
    'Pricing page inline repair: overrides cached/global muted text on the Studio card',
    "normalize(card.textContent).includes('studio')",
    "normalize(card.textContent).includes('choose studio')",
    'adminHeader.className = featureHeader.className',
    "'<th>Admin users</th><td>1</td><td>3</td><td>10</td><td>Unlimited</td>'",
] as $needle) {
    if (!str_contains($content, $needle)) {
        fwrite(STDERR, "FAILED: Pricing inline repair missing {$needle}\n");
        exit(1);
    }
}

echo "Pricing inline repair static checks passed.\n";

// End of file.
