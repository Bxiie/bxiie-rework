<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\HomeController;
use App\Platform\Tenancy\TenantContext;
use App\Tenant\Artwork\ArtworkReadRepository;
use App\Tenant\Settings\TenantSettingsRepository;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec("CREATE TABLE roles (id INTEGER, slug TEXT, scope TEXT);
CREATE TABLE role_assignments (role_id INTEGER, user_id INTEGER, tenant_id INTEGER);
INSERT INTO roles VALUES (1,'tenant_owner','tenant'),(2,'tenant_admin','tenant'),(3,'owner','tenant'),(4,'admin','tenant'),(5,'editor','tenant'),(6,'user','tenant');
INSERT INTO role_assignments VALUES (1,1,1),(2,2,1),(3,3,1),(4,4,1),(5,5,1),(6,6,1),(1,7,2);");
$tenant = new TenantContext(1, 'uuid', 'test', 'Test', 'test.local', 'custom', true);
foreach ([null, 1, 2, 3, 4, 5, 6, 7, 8] as $userId) {
    $controller = new HomeController(new TenantSettingsRepository($pdo), new ArtworkReadRepository($pdo), $pdo, currentUser: $userId === null ? null : ['user_id' => $userId]);
    $link = (new ReflectionMethod($controller, 'artworkEditLink'))->invoke($controller, $tenant, 123);
    $expected = in_array($userId, [1,2,3,4], true)
        ? '<p><a class="button artwork-edit-link" href="/admin/artworks/edit?id=123">Edit artwork</a></p>' : '';
    if ($link !== $expected) { throw new RuntimeException('Incorrect edit access for user ' . var_export($userId, true)); }
}
$source = file_get_contents(dirname(__DIR__, 2) . '/app/Http/Controllers/Tenant/HomeController.php');
if (!str_contains($source, '$body .= $this->artworkEditLink($tenant, (int) $artwork[\'id\']);')) {
    throw new RuntimeException('Artwork page does not include edit link');
}
echo "Artwork detail edit link checks passed.\n";
