<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$path = $root . '/app/Http/Controllers/Tenant/Admin/PortfolioSectionsController.php';
$contents = file_get_contents($path);
if ($contents === false) {
    fwrite(STDERR, "Unable to read PortfolioSectionsController.php\n");
    exit(1);
}

$required = [
    'aria-label="Portfolio section actions"',
    'class="admin-button" href="/admin/portfolio-sections/edit"',
    '<strong>Add portfolio section</strong>',
    '<strong>Order artwork in sections and home page</strong>',
    '<strong>Artwork placement matrix</strong>',
    'Create a new public artwork grouping.',
    'Set the display order within each section.',
    'Assign many artworks to sections at once.',
];

foreach ($required as $needle) {
    if (!str_contains($contents, $needle)) {
        fwrite(STDERR, "Missing portfolio-section action treatment: {$needle}\n");
        exit(1);
    }
}

if (str_contains($contents, 'Add portfolio section</a> · <a href="/admin/portfolio-sections/order"')) {
    fwrite(STDERR, "Legacy inline portfolio-section action links are still present.\n");
    exit(1);
}

echo "Portfolio section action static checks passed.\n";
