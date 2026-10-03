<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Admin\ArtworkPlacementController;
use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Platform\Membership\MembershipRepository;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

session_start();

function check(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

function bodyOf(object $response): string
{
    return (string) (new ReflectionProperty($response, 'body'))->getValue($response);
}

$pdo = new Pdo\Sqlite('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec(
    "CREATE TABLE roles (id INTEGER, slug TEXT, scope TEXT);
     CREATE TABLE role_assignments (role_id INTEGER, user_id INTEGER, tenant_id INTEGER);
     CREATE TABLE artworks (id INTEGER, tenant_id INTEGER, title TEXT, status TEXT, primary_media_id INTEGER);
     CREATE TABLE media_assets (id INTEGER, tenant_id INTEGER, uuid TEXT);
     CREATE TABLE portfolio_sections (id INTEGER, tenant_id INTEGER, name TEXT, slug TEXT, status TEXT, sort_order INTEGER);
     CREATE TABLE artwork_section_assignments (artwork_id INTEGER, section_id INTEGER, sort_order INTEGER);
     CREATE TABLE homepage_artwork_assignments (id INTEGER, tenant_id INTEGER, artwork_id INTEGER, sort_order INTEGER);
     INSERT INTO roles VALUES (1,'tenant_owner','tenant');
     INSERT INTO role_assignments VALUES (1,1,1);
     INSERT INTO portfolio_sections VALUES (1,1,'Landscapes','landscapes','active',0);"
);

// Mirrors the real-world shape that exposed the bug: far more artworks than
// fit on one page (page size 10 here, the smallest real option), with the
// Home-assigned ones scattered by title across many pages rather than
// clustered on page 1 — and more of them than fit on a single *filtered*
// page too, so the fix must paginate the filtered result set itself.
$homeArtworkIds = [3, 5, 7, 9, 11, 13, 15, 17, 19, 21, 23, 25, 27, 29, 31];
for ($id = 1; $id <= 50; $id++) {
    $title = sprintf('Artwork %02d', $id);
    $pdo->exec("INSERT INTO artworks (id, tenant_id, title, status) VALUES ($id, 1, '$title', 'published');");
}
foreach ($homeArtworkIds as $id) {
    $pdo->exec("INSERT INTO homepage_artwork_assignments (tenant_id, artwork_id, sort_order) VALUES (1, $id, $id);");
}
$pdo->exec('INSERT INTO artwork_section_assignments (artwork_id, section_id, sort_order) VALUES (1, 1, 0), (2, 1, 0);');

$tenant = new TenantContext(1, 'uuid', 'test', 'Test', 'test.local', 'custom', true);
$owner = ['user_id' => 1];
$controller = new ArtworkPlacementController(
    new RequireTenantRoleBrowser(new MembershipRepository($pdo)),
    $pdo,
    new CsrfTokenService(),
);
$request = new Request('GET', '/admin/artworks/placement', 'test.local', [], []);

// 1. The regression itself: filtering by Home with a small page size must
//    surface every home-assigned artwork across the whole catalog, not just
//    whichever ones happen to land on page 1 (titles 1-10 here, which
//    contains only "Artwork 03" of the four).
$_GET = ['filter' => 'home', 'per_page' => '10'];
$homeFilteredPage1 = bodyOf($controller->index($request, $tenant, $owner));
check(str_contains($homeFilteredPage1, 'Artwork 03'), 'Home filter page 1 is missing a home-assigned artwork that belongs on it');
check(!str_contains($homeFilteredPage1, 'Artwork 23'), 'Home filter page 1 should not include an artwork that belongs on a later filtered page');
check(str_contains($homeFilteredPage1, 'Showing 1–10 of 15'), 'Home filter did not report the real filtered total across the whole catalog (expected 15, the count of home-assigned artworks, not 50 total)');
check(str_contains($homeFilteredPage1, 'Filtered by Home page'), 'Summary should note the active Home filter');

$_GET = ['filter' => 'home', 'per_page' => '10', 'page' => '2'];
$homeFilteredPage2 = bodyOf($controller->index($request, $tenant, $owner));
foreach (['Artwork 23', 'Artwork 25', 'Artwork 27', 'Artwork 29', 'Artwork 31'] as $expected) {
    check(str_contains($homeFilteredPage2, $expected), "Home filter page 2 is missing {$expected}, which must appear once the filtered result set itself spans multiple pages");
}
check(!str_contains($homeFilteredPage2, 'Artwork 03'), 'Home filter page 2 should not repeat an artwork that belongs on page 1');

// 2. Without the filter, nothing is excluded — the filter is additive, not
//    a default view change.
$_GET = ['per_page' => '10'];
$unfiltered = bodyOf($controller->index($request, $tenant, $owner));
check(str_contains($unfiltered, 'Showing 1–10 of 50'), 'Unfiltered placement matrix should show the full 50-artwork catalog, paginated normally');

// 3. Section filter works the same way, and an invalid/stale section id
//    falls back to no filter rather than erroring or matching nothing.
$_GET = ['filter' => 'section-1', 'per_page' => '10'];
$sectionFiltered = bodyOf($controller->index($request, $tenant, $owner));
check(str_contains($sectionFiltered, 'Showing 1–2 of 2'), 'Section filter did not restrict to the 2 artworks assigned to that section');

$_GET = ['filter' => 'section-999', 'per_page' => '10'];
$staleSectionFilter = bodyOf($controller->index($request, $tenant, $owner));
check(str_contains($staleSectionFilter, 'Showing 1–10 of 50'), 'An unknown/stale section filter id must fall back to the unfiltered view, not error or match nothing');

// 4. The active filter's header link must toggle itself off (not require a
//    separate reset control), and reflect aria-pressed state correctly.
$_GET = ['filter' => 'home', 'per_page' => '10'];
$homeActiveBody = bodyOf($controller->index($request, $tenant, $owner));
check(str_contains($homeActiveBody, 'aria-pressed="true"'), 'Active Home filter header must report aria-pressed="true"');
check(str_contains($homeActiveBody, 'Home page ✕'), 'Active Home filter header must show an affordance to clear it');
check(!str_contains($homeActiveBody, 'href="/admin/artworks/placement?per_page=10&amp;filter=home"'), 'The active Home filter link must toggle itself OFF (clear), not link back to itself');

$_GET = ['per_page' => '10'];
$homeInactiveBody = bodyOf($controller->index($request, $tenant, $owner));
check(str_contains($homeInactiveBody, 'href="/admin/artworks/placement?per_page=10&amp;filter=home"'), 'The inactive Home filter header must link to turning the filter ON');

$_GET = [];

// 5. Static checks: the obsolete client-side-only row filter must be gone
//    (it's what silently limited filtering to the current page), the
//    column-visibility search must remain, and the filter persists across
//    pagination links via $base.
$controllerSource = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Http/Controllers/Tenant/Admin/ArtworkPlacementController.php');
foreach ([
    'normalizedAssignmentFilter method' => 'private function normalizedAssignmentFilter(string $raw, array $sections): string',
    'filterToggleHref method' => 'private function filterToggleHref(string $path, array $base, string $filterValue, bool $active): string',
    'artworksPage accepts filter' => 'private function artworksPage(TenantContext $tenant, string $q, int $page, int $pageSize, string $filter = \'\'): array',
    'filter applied server-side for home' => "if (\$filter === 'home') {",
    'base carries filter for pagination' => "'filter' => \$filter,",
] as $label => $needle) {
    if (!str_contains($controllerSource, $needle)) {
        throw new RuntimeException('ArtworkPlacementController missing ' . $label);
    }
}

$jsSource = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/artwork-pagination.js');
if (str_contains($jsSource, 'data-placement-assignment-filter') || str_contains($jsSource, 'placementAssignmentFilter')) {
    throw new RuntimeException('artwork-pagination.js still contains the obsolete client-side-only row assignment filter.');
}
if (!str_contains($jsSource, 'data-placement-column-reset')) {
    throw new RuntimeException('artwork-pagination.js lost the legitimate column-visibility search/reset feature.');
}

echo "Artwork placement Home/section filter checks passed.\n";

// End of file.
