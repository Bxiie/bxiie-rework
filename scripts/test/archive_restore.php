<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Http\Controllers\Tenant\Admin\ArchiveController;
use App\Http\Controllers\Tenant\Admin\ArtworksController;
use App\Http\Controllers\Tenant\Admin\PortfolioSectionsController;
use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Membership\MembershipRepository;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Archive\ArchivedItemRepository;

// Translate only the two MySQL aggregate expressions used by the artwork list.
final class ArchiveTestPdo extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        $query = str_replace("GROUP_CONCAT(atype.code ORDER BY atype.code SEPARATOR ',')", "GROUP_CONCAT(atype.code, ',')", $query);
        $query = str_replace("GROUP_CONCAT(ps.name ORDER BY LOWER(ps.name), ps.name SEPARATOR '||')", "GROUP_CONCAT(ps.name, '||')", $query);
        return parent::prepare($query, $options);
    }
}
session_start();
$pdo = new ArchiveTestPdo('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec("CREATE TABLE roles (id INTEGER, slug TEXT, scope TEXT);
CREATE TABLE role_assignments (role_id INTEGER, user_id INTEGER, tenant_id INTEGER);
INSERT INTO roles VALUES (1,'tenant_admin','tenant'),(2,'editor','tenant');
INSERT INTO role_assignments VALUES (1,1,1),(2,2,1),(1,3,2);
CREATE TABLE portfolio_sections (id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT, slug TEXT, description TEXT, show_as_tab INTEGER, sort_order INTEGER, status TEXT, created_at TEXT, updated_at TEXT);
INSERT INTO portfolio_sections VALUES (1,1,'Archived section','old-section','Keep description',1,7,'archived','created','updated'),(2,1,'Active section','current','',1,8,'active','created','updated'),(3,2,'Foreign section','foreign','',1,9,'archived','created','updated');
CREATE TABLE artworks (id INTEGER PRIMARY KEY, tenant_id INTEGER, title TEXT, slug TEXT, description TEXT, medium TEXT, year_created TEXT, status TEXT, scheduled_publish_at TEXT, sale_status TEXT, price TEXT, is_one_off INTEGER, inventory_quantity INTEGER, primary_media_id INTEGER, created_at TEXT, updated_at TEXT);
INSERT INTO artworks VALUES (1,1,'Archived artwork','archived','Keep artwork','Oil','2020','archived','2030-01-01','for_sale','100',1,1,1,'created','updated'),(2,1,'Published artwork','published','','Oil','2021','published',NULL,'nfs','',1,1,NULL,'created','updated'),(3,2,'Foreign artwork','foreign','','Oil','2022','archived',NULL,'nfs','',1,1,NULL,'created','updated');
CREATE TABLE media_assets (id INTEGER, uuid TEXT, storage_path TEXT, mime_type TEXT, width INTEGER, height INTEGER);
INSERT INTO media_assets VALUES (1,'image-uuid','image.jpg','image/jpeg',100,100);
CREATE TABLE tenant_settings (tenant_id INTEGER, setting_key TEXT, setting_value TEXT);
CREATE TABLE artwork_types (id INTEGER, code TEXT);
CREATE TABLE artwork_type_assignments (artwork_id INTEGER, type_id INTEGER);
CREATE TABLE artwork_section_assignments (artwork_id INTEGER, section_id INTEGER, sort_order INTEGER);
INSERT INTO artwork_section_assignments VALUES (1,1,4);
CREATE TABLE artwork_release_group_items (tenant_id INTEGER, artwork_id INTEGER, release_group_id INTEGER);
INSERT INTO artwork_release_group_items VALUES(1,1,10),(2,3,20);
CREATE TABLE audit_log (id INTEGER PRIMARY KEY, tenant_id INTEGER, user_id INTEGER, action TEXT, entity_type TEXT, entity_id TEXT, details TEXT, ip_address TEXT);");
$tenant = new TenantContext(1,'uuid','test','Test','test.local','custom',true);
$roles = new RequireTenantRoleBrowser(new MembershipRepository($pdo));
$csrf = new CsrfTokenService();
$token = $csrf->getOrCreate();
$repository = new ArchivedItemRepository($pdo);
$controller = new ArchiveController($roles, $repository, $csrf);
$sections = new PortfolioSectionsController($roles, $pdo, $csrf);
$artworks = new ArtworksController($roles, $pdo, new AuditLogRepository($pdo), $csrf);
$admin = ['user_id'=>1];
$count=0;
function check(bool $ok, string $message): void { global $count; if (!$ok) throw new RuntimeException($message); $count++; }
function field(object $object, string $name): mixed { return (new ReflectionProperty($object, $name))->getValue($object); }
$get = new Request('GET','/admin/artworks','test.local',[],[]);
$post = new Request('POST','/admin/artworks/restore','test.local',[],[]);
foreach ([['view'=>'archived'],['view'=>'all'],[]] as $query) {
    $_GET=$query;
    $html=field($sections->index($get,$tenant,$admin),'body');
    check(str_contains($html,'Archived section</strong>') === ($query !== []), 'Section archive visibility');
    check(str_contains($html,'Active section</strong>') === (($query['view'] ?? '') !== 'archived'), 'Section current visibility');
    check(!str_contains($html,'Foreign section'), 'Sections tenant isolation');
    check(str_contains($html,'Restore as hidden') === ($query !== []), 'Section restore action only on archived rows');
}
foreach ([['status'=>'archived'],['show_archived'=>'1'],[]] as $query) {
    $_GET=$query;
    $html=field($artworks->index($get,$tenant,$admin),'body');
    check(str_contains($html,'Archived artwork</strong>') === ($query !== []), 'Artwork archive visibility');
    check(str_contains($html,'Published artwork</strong>') === (($query['status'] ?? '') !== 'archived'), 'Artwork current visibility');
    check(!str_contains($html,'Foreign artwork'), 'Artwork tenant isolation');
    check(str_contains($html,'Restore as draft') === ($query !== []), 'Artwork restore action only on archived rows');
}
foreach (['section','artwork'] as $kind) {
    $_POST=['id'=>1,'csrf_token'=>$token];
    foreach ([null,['user_id'=>2],['user_id'=>3]] as $user) check(field($controller->restore($post,$tenant,$user,$kind),'status')===403,'Unauthorized restoration denied');
    check(field($controller->restore($get,$tenant,$admin,$kind),'status')===405,'GET does not restore');
    $_POST['csrf_token']='bad';
    check(field($controller->restore($post,$tenant,$admin,$kind),'status')===419,'CSRF required');
    $_POST=['id'=>3,'csrf_token'=>$token];
    check(field($controller->restore($post,$tenant,$admin,$kind),'status')===404,'Foreign tenant item inaccessible');
    $_POST['id']=999;
    check(field($controller->restore($post,$tenant,$admin,$kind),'status')===404,'Missing item fails cleanly');
    $_POST['id']=2;
    check(field($controller->restore($post,$tenant,$admin,$kind),'status')===404,'Non-archived status unchanged');
    $_POST['id']=1;
    $response=$controller->restore($post,$tenant,$admin,$kind);
    check(field($response,'status')===303,'Restore redirects');
    check(str_contains(field($response,'headers')['Location'],'restored'),'Successful restoration notice');
    check(field($controller->restore($post,$tenant,$admin,$kind),'status')===404,'Repeated restore cannot change status');
}
$section=$pdo->query('SELECT * FROM portfolio_sections WHERE id=1')->fetch();
$artwork=$pdo->query('SELECT * FROM artworks WHERE id=1')->fetch();
check($section['status']==='hidden' && $section['slug']==='old-section' && $section['sort_order']===7 && $section['show_as_tab']===1,'Section restored hidden with metadata intact');
check($artwork['status']==='draft' && $artwork['primary_media_id']===1 && $artwork['price']==='100','Artwork restored draft with media and commerce intact');
check($artwork['scheduled_publish_at']===null,'Individual publication schedule cleared');
check($pdo->query('SELECT COUNT(*) FROM artwork_release_group_items WHERE tenant_id=1')->fetchColumn()===0,'Release membership cleared');
check($pdo->query('SELECT COUNT(*) FROM artwork_release_group_items WHERE tenant_id=2')->fetchColumn()===1,'Other tenant release untouched');
check($pdo->query('SELECT COUNT(*) FROM artwork_section_assignments WHERE artwork_id=1 AND section_id=1 AND sort_order=4')->fetchColumn()===1,'Portfolio assignments retained');
check($pdo->query('SELECT COUNT(*) FROM audit_log')->fetchColumn()===2,'Successful restores audited once');
$pdo->exec("UPDATE artworks SET status='archived',scheduled_publish_at='2030-01-01' WHERE id=1; INSERT INTO artwork_release_group_items VALUES(1,1,10); CREATE TRIGGER fail_audit BEFORE INSERT ON audit_log BEGIN SELECT RAISE(ABORT,'test rollback'); END;");
try { $repository->restore($tenant,'artwork',1,1); throw new RuntimeException('Expected audit failure'); } catch (PDOException) {}
check($pdo->query('SELECT status FROM artworks WHERE id=1')->fetchColumn()==='archived','Failure rolls status back');
check($pdo->query('SELECT scheduled_publish_at FROM artworks WHERE id=1')->fetchColumn()==='2030-01-01','Failure rolls schedule back');
check($pdo->query('SELECT COUNT(*) FROM artwork_release_group_items WHERE tenant_id=1')->fetchColumn()===1,'Failure rolls release membership back');
echo "Archive viewing and restore: $count checks passed.\n";
