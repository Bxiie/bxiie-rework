<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$homePath = $root . '/app/Http/Controllers/Tenant/HomeController.php';
$adminPath = $root . '/app/Http/Controllers/Tenant/Admin/ArtworksController.php';

$errors = [];

foreach ([$homePath, $adminPath] as $path) {
    if (!is_file($path)) {
        $errors[] = "Missing required file: {$path}";
    }
}

if ($errors === []) {
    $home = (string) file_get_contents($homePath);
    $admin = (string) file_get_contents($adminPath);

    $checks = [
        'public HomeController has a cents formatter' =>
            str_contains($home, 'private function money(int $cents): string')
            && str_contains($home, 'number_format(max(0, $cents) / 100, 2)'),

        'multi-variant control formats resolved variant prices' =>
            str_contains($home, '$this->money((int) ($variant[\'resolved_price_cents\']'),

        'artwork editor shows Home Page checkbox' =>
            str_contains($admin, 'name="homepage_selected"')
            && str_contains($admin, 'homepage-special-section-option'),

        'artwork editor loads Home Page assignment state' =>
            str_contains($admin, '$this->artworkIsOnHomePage($tenant, $id)'),

        'artwork update saves Home Page assignment' =>
            str_contains($admin, '$this->replaceHomepageAssignment('),

        'Home Page assignment is tenant scoped' =>
            str_contains($admin, 'DELETE FROM homepage_artwork_assignments')
            && str_contains($admin, 'tenant_id = :tenant_id')
            && str_contains($admin, 'artwork_id = :artwork_id'),

        'site-only records are excluded through portfolio type requirement' =>
            str_contains(
                $admin,
                'in_array(\'portfolio_images\', $typeCodes, true)'
            ),

        'existing Home Page order is preserved' =>
            str_contains($admin, '$existingSortOrder = $existing->fetchColumn()')
            && str_contains($admin, 'if ($existingSortOrder !== false)'),

        'new Home Page assignments append in order' =>
            str_contains($admin, 'COALESCE(MAX(sort_order), 0) + 10'),
    ];

    foreach ($checks as $label => $passed) {
        if (!$passed) {
            $errors[] = $label;
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "[FAIL] Public variant detail / Home Page editor static check failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "[FAIL]  - {$error}\n");
    }
    exit(1);
}

echo "[PASS] Public variant detail / Home Page editor static check passed.\n";

// End of file.
