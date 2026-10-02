<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Admin\ArtworksController;
use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Platform\Audit\AuditLogRepository;
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

// Translate only the MariaDB-specific GROUP_CONCAT(... ORDER BY ... SEPARATOR
// '...') syntax to SQLite's GROUP_CONCAT(expr, 'sep'); queries, parameters
// and policy run unchanged. Ordering inside the aggregate doesn't matter for
// what this test checks.
final class HomeFilterTestPdo extends Pdo\Sqlite
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'GROUP_CONCAT')) {
            $query = preg_replace(
                "/GROUP_CONCAT\\(([\\w.]+)\\s+ORDER BY\\s+.+?\\s+SEPARATOR\\s+'([^']*)'\\s*\\)/s",
                "GROUP_CONCAT(\$1, '\$2')",
                $query,
            );
        }

        return parent::prepare((string) $query, $options);
    }
}

$pdo = new HomeFilterTestPdo('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->createFunction('DATABASE', fn () => 'test');
$pdo->exec(
    "ATTACH DATABASE ':memory:' AS information_schema;
     CREATE TABLE information_schema.tables (table_schema TEXT, table_name TEXT);
     CREATE TABLE roles (id INTEGER, slug TEXT, scope TEXT);
     CREATE TABLE role_assignments (role_id INTEGER, user_id INTEGER, tenant_id INTEGER);
     CREATE TABLE audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, action TEXT, entity_type TEXT, entity_id TEXT, details TEXT, ip_address TEXT, created_at TEXT);
     CREATE TABLE artworks (id INTEGER, tenant_id INTEGER, title TEXT, slug TEXT, description TEXT, medium TEXT, year_created TEXT, status TEXT, scheduled_publish_at TEXT, sale_status TEXT, price TEXT, is_one_off INTEGER, inventory_quantity INTEGER, primary_media_id INTEGER, sort_order INTEGER, created_at TEXT);
     CREATE TABLE media_assets (id INTEGER, tenant_id INTEGER, uuid TEXT, storage_path TEXT, mime_type TEXT, width INTEGER, height INTEGER);
     CREATE TABLE artwork_types (id INTEGER, code TEXT);
     CREATE TABLE artwork_type_assignments (artwork_id INTEGER, type_id INTEGER);
     CREATE TABLE portfolio_sections (id INTEGER, tenant_id INTEGER, slug TEXT, name TEXT, status TEXT, show_as_tab INTEGER, sort_order INTEGER);
     CREATE TABLE artwork_section_assignments (artwork_id INTEGER, section_id INTEGER, sort_order INTEGER);
     CREATE TABLE homepage_artwork_assignments (id INTEGER, tenant_id INTEGER, artwork_id INTEGER, sort_order INTEGER);
     CREATE TABLE tenant_settings (tenant_id INTEGER, setting_key TEXT, setting_value TEXT);
     INSERT INTO roles VALUES (1,'tenant_owner','tenant');
     INSERT INTO role_assignments VALUES (1,1,1);
     INSERT INTO artwork_types VALUES (1,'portfolio_images');"
);

foreach ([1 => 'On Home A', 2 => 'On Home B', 3 => 'On Home C', 4 => 'Not On Home'] as $id => $title) {
    $pdo->exec("INSERT INTO artworks (id, tenant_id, title, slug, status, sort_order) VALUES ($id, 1, '$title', 'slug-$id', 'published', 0);
    INSERT INTO artwork_type_assignments VALUES ($id, 1);");
}
$pdo->exec('INSERT INTO homepage_artwork_assignments (id, tenant_id, artwork_id, sort_order) VALUES (1, 1, 1, 10), (2, 1, 2, 20), (3, 1, 3, 30);');

// A separate batch of 11 home-assigned artworks (ids 100-110), just to force
// a second page at the smallest real page size (10 — Pagination::
// standardPageSizes() only allows multiples of ten, so a smaller per_page
// would silently fall back to the default and never paginate).
for ($id = 100; $id <= 110; $id++) {
    $pdo->exec("INSERT INTO artworks (id, tenant_id, title, slug, status, sort_order) VALUES ($id, 1, 'Home Filler $id', 'slug-$id', 'published', 0);
    INSERT INTO artwork_type_assignments VALUES ($id, 1);
    INSERT INTO homepage_artwork_assignments (tenant_id, artwork_id, sort_order) VALUES (1, $id, $id);");
}

$tenant = new TenantContext(1, 'uuid', 'test', 'Test', 'test.local', 'custom', true);
$owner = ['user_id' => 1];
$controller = new ArtworksController(
    new RequireTenantRoleBrowser(new MembershipRepository($pdo)),
    $pdo,
    new AuditLogRepository($pdo),
    new CsrfTokenService(),
);
$request = new Request('GET', '/admin/artworks', 'test.local', [], []);

// 1. ?section_id=home must restrict the list to artworks on the Home Page
//    selection, and only those.
$_GET = ['section_id' => 'home'];
$homeFilteredBody = bodyOf($controller->index($request, $tenant, $owner));
check(str_contains($homeFilteredBody, 'On Home A'), 'Home filter dropped an artwork that is on the Home Page');
check(str_contains($homeFilteredBody, 'On Home B'), 'Home filter dropped an artwork that is on the Home Page');
check(str_contains($homeFilteredBody, 'On Home C'), 'Home filter dropped an artwork that is on the Home Page');
check(!str_contains($homeFilteredBody, 'Not On Home'), 'Home filter included an artwork that is not on the Home Page');
check(str_contains($homeFilteredBody, '<option value="home" selected>Home Page</option>'), 'Home Page dropdown option was not marked selected while filtering by it');

// 2. Without the filter, all four show, and the option exists but isn't
//    selected — it must be an available, not a forced, filter.
$_GET = [];
$unfilteredBody = bodyOf($controller->index($request, $tenant, $owner));
foreach (['On Home A', 'On Home B', 'On Home C', 'Not On Home'] as $title) {
    check(str_contains($unfilteredBody, $title), "Unfiltered list is missing {$title}");
}
check(str_contains($unfilteredBody, '<option value="home">Home Page</option>'), 'Home Page option is missing from the Section filter dropdown');

// 3. The filter must survive onto pagination links (via $baseQuery), not
//    just the dropdown's own selected state. Force a second page with
//    per_page=2 against the 3 home-assigned artworks and check its href.
$_GET = ['section_id' => 'home', 'per_page' => '10'];
$pagedBody = bodyOf($controller->index($request, $tenant, $owner));
check(
    str_contains($pagedBody, 'section_id=home') && str_contains($pagedBody, 'page=2'),
    'section_id=home was not carried onto the pagination links for a filtered, multi-page result',
);
$_GET = [];

echo "Admin artworks Home Page filter checks passed.\n";

// End of file.
