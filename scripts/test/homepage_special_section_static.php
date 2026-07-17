<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$files = [
    'controller' => $root . '/app/Http/Controllers/Tenant/Admin/HomepageArtworksController.php',
    'sections' => $root . '/app/Http/Controllers/Tenant/Admin/PortfolioSectionsController.php',
    'routes' => $root . '/app/Http/Routes/tenant.php',
    'metadata_test' => $root . '/scripts/test/training_artwork_metadata_fixtures_static.php',
];

$content = [];
$errors = [];

foreach ($files as $name => $path) {
    if (!is_file($path)) {
        $errors[] = "Missing {$name}: {$path}";
        continue;
    }

    $content[$name] = (string) file_get_contents($path);
}

if ($errors === []) {
    $checks = [
        'special section card exists' =>
            str_contains(
                $content['sections'],
                'homepage-special-section-card'
            ),

        'GET route exists' =>
            str_contains(
                $content['routes'],
                "\$router->get('/admin/portfolio-sections/home-page'"
            ),

        'POST route exists' =>
            str_contains(
                $content['routes'],
                "\$router->post('/admin/portfolio-sections/home-page'"
            ),

        'controller reads assignments' =>
            str_contains(
                $content['controller'],
                'homepage_artwork_assignments h'
            ),

        'controller replaces tenant assignments transactionally' =>
            str_contains(
                $content['controller'],
                'DELETE FROM homepage_artwork_assignments WHERE tenant_id = :tenant_id'
            )
            && str_contains(
                $content['controller'],
                'beginTransaction()'
            ),

        'site artwork is excluded' =>
            str_contains(
                $content['controller'],
                'at.code = "site"'
            ),

        'draft artwork may be selected' =>
            str_contains(
                $content['controller'],
                'HOME_PAGE_ALLOWS_DRAFT_ARTWORK'
            )
            && !str_contains(
                $content['controller'],
                'a.status = "published"'
            ),

        'CSRF is validated' =>
            str_contains(
                $content['controller'],
                '$this->csrf->validate'
            ),

        'metadata test scopes slug checks to artworkPlan' =>
            str_contains(
                $content['metadata_test'],
                '$artworkPlanSource'
            ),
    ];

    foreach ($checks as $label => $passed) {
        if (!$passed) {
            $errors[] = $label;
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "[FAIL] Home Page special-section static check failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "[FAIL]  - {$error}\n");
    }
    exit(1);
}

echo "[PASS] Home Page special-section static check passed.\n";

// End of file.
