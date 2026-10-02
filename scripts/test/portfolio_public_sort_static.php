<?php

declare(strict_types=1);

// Static regression check for the public /portfolio "Sort by" control.
// Confirms the visitor-facing sort select exists, its options map onto the
// ArtworkReadRepository::orderSql() branches, and the chosen order is
// threaded through pagination/section links so navigating pages or
// sections does not silently reset a visitor's sort choice.

$root = dirname(__DIR__, 2);
$failures = [];

$controllerPath = $root . '/app/Http/Controllers/Tenant/HomeController.php';
$controller = file_get_contents($controllerPath) ?: '';

foreach ([
    'resolveSortOrder marker' => '$sortOrder = $this->resolveSortOrder((string) ($_GET[\'sort\'] ?? \'\'), $tenantDefaultOrder);',
    'publishedPage uses resolved sort' => "\$sortOrder,\n            \$sectionSlug !== '' ? \$sectionSlug : null,",
    'sort select markup' => '<label>Sort by<br><select name="sort">\' . $sortOptions . \'</select></label>',
    'sortOptionLabels method' => 'private function sortOptionLabels(): array',
    'resolveSortOrder method' => 'private function resolveSortOrder(string $requested, string $tenantDefault): string',
    'date_desc option' => "'date_desc' => 'Date (newest first)'",
    'date option' => "'date' => 'Date (oldest first)'",
    'name option' => "'name' => 'Name'",
    'medium (materials) option' => "'medium' => 'Materials'",
    'manual option' => "'manual' => 'Curated order'",
    'all-artwork link carries sort' => "\$allHref = '/portfolio?' . http_build_query(['per_page' => \$pageSize, 'sort' => \$sortOrder]);",
    'section tab links carry sort' => "'sort' => \$sortOrder,",
    'pageStepLink accepts sort' => 'private function pageStepLink(string $path, string $sectionSlug, int $pageSize, int $page, string $label, bool $disabled, string $sortOrder = \'date_desc\'): string',
] as $label => $needle) {
    if (!str_contains($controller, $needle)) {
        $failures[] = "HomeController missing {$label}";
    }
}

// Every pagination/page-step query array built in portfolio() must include sort,
// otherwise paging or stepping would silently drop the visitor's chosen order.
if (!preg_match("/\\\$query = \\['page' => \\\$pageNumber, 'per_page' => \\\$pageSize, 'sort' => \\\$sortOrder\\];/", $controller)) {
    $failures[] = 'HomeController pagination loop does not carry sort on every page link';
}

$repositoryPath = $root . '/app/Tenant/Artwork/ArtworkReadRepository.php';
$repository = file_get_contents($repositoryPath) ?: '';

foreach ([
    "'name' => 'a.title ASC, a.id ASC'",
    "'medium' => 'a.medium ASC, a.title ASC, a.id ASC'",
    "'date_desc' => \"CAST(NULLIF(a.year_created, '') AS UNSIGNED) DESC, a.title ASC, a.id ASC\"",
] as $needle) {
    if (!str_contains($repository, $needle)) {
        $failures[] = "ArtworkReadRepository::orderSql missing expected branch: {$needle}";
    }
}

$jsPath = $root . '/public/assets/artwork-pagination.js';
$js = file_get_contents($jsPath) ?: '';
if (!str_contains($js, 'select[name="per_page"], ${rootSelector} select[name="sort"]')) {
    $failures[] = 'artwork-pagination.js change listener does not auto-submit the sort select';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Public portfolio sort static check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

fwrite(STDOUT, "[PASS] Public portfolio sort static check passed.\n");

// End of file.
