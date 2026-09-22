<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Admin\PortfolioSectionsController;
use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Platform\Membership\MembershipRepository;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
session_start();
function check(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
function field(object $object, string $name): mixed {
    return (new ReflectionProperty($object, $name))->getValue($object);
}
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec("CREATE TABLE roles (id INTEGER, slug TEXT, scope TEXT);
CREATE TABLE role_assignments (role_id INTEGER, user_id INTEGER, tenant_id INTEGER);
INSERT INTO roles VALUES (1, 'tenant_admin', 'tenant'), (2, 'editor', 'tenant');
INSERT INTO role_assignments VALUES (1, 1, 1), (2, 2, 1), (1, 3, 2);
CREATE TABLE portfolio_sections (id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT, slug TEXT, description TEXT, show_as_tab INTEGER, sort_order INTEGER, status TEXT, created_at TEXT, updated_at TEXT);
CREATE TABLE artwork_section_assignments (artwork_id INTEGER, section_id INTEGER, sort_order INTEGER);
INSERT INTO artwork_section_assignments VALUES (10, 1, 55);");
$insert = $pdo->prepare('INSERT INTO portfolio_sections VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?)');
foreach ([[1,1,'zebra',8,'active'], [2,1,'Alpha',9,'hidden'], [3,1,'alpha',6,'active'], [4,1,'Beta',3,'active'], [5,1,'A archived',77,'archived'], [6,2,'Other tenant',88,'active']] as [$id,$tenantId,$name,$order,$status]) {
    $insert->execute([$id,$tenantId,$name,'slug-'.$id,'Contents '.$id,$order,$status,'created','updated']);
}
$tenant = new TenantContext(1, 'uuid', 'test', 'Test', 'test.local', 'custom', true);
$csrf = new CsrfTokenService();
$controller = new PortfolioSectionsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, $csrf);
$request = new Request('POST', '/admin/portfolio-sections/alphabetize', 'test.local', [], []);
$snapshot = fn () => $pdo->query('SELECT * FROM portfolio_sections ORDER BY id')->fetchAll();
$before = $snapshot();
$_POST = ['csrf_token' => $csrf->getOrCreate(), 'confirmed' => '1'];
foreach ([null, ['user_id'=>2], ['user_id'=>3]] as $user) {
    check(field($controller->alphabetize($request, $tenant, $user), 'status') === 403, 'Unauthorized access');
}
$admin = ['user_id'=>1];
check(field($controller->alphabetize(new Request('GET', '/', '', [], []), $tenant, $admin), 'status') === 405, 'GET accepted');
foreach ([['csrf_token'=>'bad','confirmed'=>'1'], ['confirmed'=>'1'], ['csrf_token'=>$csrf->getOrCreate(),'confirmed'=>'0']] as $post) {
    $_POST = $post;
    $response = $controller->alphabetize($request, $tenant, $admin);
    check(field($response, 'status') === 303, 'Invalid request did not redirect');
    check(str_contains($_SESSION['portfolio_sections_alphabetize'][1], 'not reordered'), 'Missing error flash');
}
check($snapshot() === $before, 'Rejected requests changed data');
$_POST = ['csrf_token'=>$csrf->getOrCreate(), 'confirmed'=>'1'];
$response = $controller->alphabetize($request, $tenant, $admin);
check(field($response, 'headers')['Location'] === '/admin/portfolio-sections', 'Wrong redirect');
check($_SESSION['portfolio_sections_alphabetize'][1] === 'Portfolio sections alphabetized.', 'Missing success flash');
$expected = $before;
foreach ([4,1,2,3,77,88] as $index => $order) { $expected[$index]['sort_order'] = $order; }
check($snapshot() === $expected, 'Incorrect order or non-order fields changed');
check($pdo->query('SELECT * FROM artwork_section_assignments')->fetchAll() === [['artwork_id'=>10,'section_id'=>1,'sort_order'=>55]], 'Artwork contents changed');
$controller->alphabetize($request, $tenant, $admin);
check($snapshot() === $expected, 'Repeated sorting is not deterministic');
$pdo->exec('UPDATE portfolio_sections SET sort_order = 50 WHERE tenant_id = 1');
$beforeFailure = $snapshot();
$pdo->exec("CREATE TRIGGER fail_sort BEFORE UPDATE ON portfolio_sections WHEN NEW.id = 3 BEGIN SELECT RAISE(ABORT, 'test failure'); END");
$controller->alphabetize($request, $tenant, $admin);
check($snapshot() === $beforeFailure, 'Failure did not roll back earlier updates');
check(str_contains($_SESSION['portfolio_sections_alphabetize'][1], 'Unable'), 'Missing failure flash');
$pdo->exec('DROP TRIGGER fail_sort');
// Exercise actual page rendering as well as the mutation handler.
foreach ([0, 2] as $sectionId) {
    $_GET = $sectionId ? ['id' => (string) $sectionId] : [];
    $page = $controller->edit(new Request('GET', '/admin/portfolio-sections/edit', 'test.local', $_GET, []), $tenant, $admin);
    check(field($page, 'status') === 200, 'Section edit page failed to render');
    check(str_contains(field($page, 'body'), 'name="csrf_token"'), 'Edit page has no CSRF field');
    check(str_contains(field($page, 'body'), 'name="name"'), 'Edit page has no name field');
}
$_GET = [];
$_SESSION['portfolio_sections_alphabetize'][1] = 'Portfolio sections alphabetized.';
$page = $controller->index(new Request('GET', '/admin/portfolio-sections', 'test.local', [], []), $tenant, $admin);
check(field($page, 'status') === 200, 'Section listing failed to render');
check(str_contains(field($page, 'body'), 'Alphabetize Sections'), 'Alphabetize button missing from rendered page');
check(str_contains(field($page, 'body'), 'Portfolio sections alphabetized.'), 'Flash missing from rendered page');
check(!isset($_SESSION['portfolio_sections_alphabetize'][1]), 'Rendered flash was not consumed');
$page = $controller->index(new Request('GET', '/admin/portfolio-sections', 'test.local', [], []), $tenant, ['user_id' => 2]);
check(!str_contains(field($page, 'body'), 'Alphabetize Sections'), 'Contributor can see admin action');
$pdo->exec('DELETE FROM portfolio_sections WHERE tenant_id = 1');
$controller->alphabetize($request, $tenant, $admin);
check($_SESSION['portfolio_sections_alphabetize'][1] === 'Portfolio sections alphabetized.', 'Empty list failed');
$source = file_get_contents(dirname(__DIR__, 2) . '/app/Http/Controllers/Tenant/Admin/PortfolioSectionsController.php');
check(str_contains($source, 'onsubmit="if (!confirm(') && str_contains($source, 'this.elements.confirmed.value'), 'Confirmation missing');
check(str_contains($source, 'method="post" action="/admin/portfolio-sections/alphabetize"'), 'POST form missing');
check(str_contains($source, 'unset($_SESSION[\'portfolio_sections_alphabetize\'][$tenant->tenantId])'), 'Flash not consumed');
$routes = file_get_contents(dirname(__DIR__, 2) . '/app/Http/Routes/tenant.php');
check(str_contains($routes, '$router->post(\'/admin/portfolio-sections/alphabetize\''), 'POST route missing');
check(!str_contains($routes, '$router->get(\'/admin/portfolio-sections/alphabetize\''), 'GET route registered');
echo "Portfolio sections alphabetize integration checks passed.\n";
