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
$pdo = new \Pdo\Sqlite('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
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
$pdo->exec('ALTER TABLE portfolio_sections ADD COLUMN uuid TEXT');
$pdo->exec('CREATE UNIQUE INDEX uq_section_slug ON portfolio_sections(tenant_id, slug)');
$pdo->createFunction('UUID', fn () => bin2hex(random_bytes(16)));
$admin = ['user_id' => 1];
$before = $snapshot();
$base = ['csrf_token' => $csrf->getOrCreate(), 'name' => 'New <section>', 'description' => 'Keep <this>', 'sort_order' => '42', 'status' => 'hidden', 'show_as_tab' => '1'];
foreach ([0, 2] as $id) {
    foreach (['slug-1', 'slug-5'] as $slug) {
        $_GET = [];
        $_POST = $base + ['id' => (string) $id, 'slug' => $slug];
        $response = $controller->update($request, $tenant, $admin);
        check(field($response, 'status') === 422, 'Duplicate slug should return validation error');
        $html = field($response, 'body');
        foreach (['role="alert"', 'already used', 'New &lt;section&gt;', 'Keep &lt;this&gt;', 'value="42"', 'name="id" value="'.$id.'"', 'value="hidden" selected', 'value="1" checked'] as $text) {
            check(str_contains($html, $text), 'Validation form did not preserve: '.$text);
        }
        check(!str_contains($html, '<section>'), 'Submitted markup not escaped');
        check($snapshot() === $before, 'Duplicate request changed existing data');
    }
}
// Auto-generated slugs must receive the same validation as explicit slugs.
$_POST = array_replace($base, ['name' => 'Slug 1', 'slug' => '']);
check(field($controller->update($request, $tenant, $admin), 'status') === 422, 'Generated duplicate slug failed');
// An existing record can keep its own slug.
$_POST = array_replace($base, ['id' => '2', 'slug' => 'slug-2']);
check(field($controller->update($request, $tenant, $admin), 'status') === 303, 'Unchanged slug rejected');
// Other tenants do not reserve a slug for this tenant.
$_POST = $base + ['slug' => 'slug-6'];
check(field($controller->update($request, $tenant, $admin), 'status') === 303, 'Other tenant slug rejected');
// Unrelated integrity failures must remain errors, not misleading slug notices.
$pdo->exec("CREATE TRIGGER unrelated_failure BEFORE INSERT ON portfolio_sections BEGIN SELECT RAISE(ABORT, 'unrelated integrity error'); END");
$_POST = $base + ['slug' => 'unique-slug'];
$caught = false;
try { $controller->update($request, $tenant, $admin); } catch (PDOException $e) { $caught = true; }
check($caught, 'Unrelated database error was swallowed');
echo "Portfolio section duplicate slug checks passed.\n";
