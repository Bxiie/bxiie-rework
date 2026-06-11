<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$file = $root . '/app/Http/Controllers/Platform/PricingController.php';
$content = (string) file_get_contents($file);

foreach ([
    'Pricing page final polish: Studio contrast',
    "['professional', 'Choose Professional', '/signup?plan=professional']",
    "['collective', 'Choose Collective', '/signup?plan=collective']",
    "['studio', 'Choose Studio', '/signup?plan=studio']",
    "action.style.setProperty('color', '#111111', 'important')",
    "adminHeader.className = '';",
    "adminHeader.textContent = 'Admin users';",
    "adminRow.innerHTML = '<td>Admin users</td><td>1</td><td>3</td><td>10</td><td>Unlimited</td>';",
] as $needle) {
    if (!str_contains($content, $needle)) {
        fwrite(STDERR, "FAILED: pricing final polish missing {$needle}\n");
        exit(1);
    }
}

echo "Pricing final polish static checks passed.\n";

// End of file.
