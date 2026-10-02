<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$pdo = new Pdo\Sqlite('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->createFunction('DATABASE', fn () => 'test');
$pdo->exec("ATTACH DATABASE ':memory:' AS information_schema;
CREATE TABLE information_schema.tables (table_schema TEXT, table_name TEXT);
INSERT INTO information_schema.tables VALUES ('test','homepage_artwork_assignments');
CREATE TABLE artworks (id INTEGER, tenant_id INTEGER, uuid TEXT, title TEXT, slug TEXT, description TEXT, notes_html TEXT, medium TEXT, dimensions TEXT, year_created TEXT, status TEXT, sale_status TEXT, price INTEGER, is_one_off INTEGER, inventory_quantity INTEGER, primary_media_id INTEGER, sort_order INTEGER, created_at TEXT);
CREATE TABLE media_assets (id INTEGER, tenant_id INTEGER, uuid TEXT, alt_text TEXT, is_private INTEGER);
CREATE TABLE artwork_types (id INTEGER, code TEXT);
CREATE TABLE artwork_type_assignments (artwork_id INTEGER, type_id INTEGER);
CREATE TABLE homepage_artwork_assignments (id INTEGER, tenant_id INTEGER, artwork_id INTEGER, sort_order INTEGER);
CREATE TABLE portfolio_sections (id INTEGER, tenant_id INTEGER, slug TEXT, name TEXT, status TEXT, show_as_tab INTEGER, sort_order INTEGER);
CREATE TABLE artwork_section_assignments (artwork_id INTEGER, section_id INTEGER, sort_order INTEGER);
INSERT INTO artwork_types VALUES (1,'portfolio_images');
INSERT INTO portfolio_sections VALUES (1,1,'all','All','active',1,0);");
foreach ([1=>'published',2=>'draft',3=>'archived'] as $id=>$status) {
    $pdo->exec("INSERT INTO artworks VALUES ($id,1,'uuid-$id','Title $id','slug-$id','','','','','2026','$status','nfs',0,1,1,$id,0,'2026');
    INSERT INTO media_assets VALUES ($id,1,'media-$id','',0);
    INSERT INTO artwork_type_assignments VALUES ($id,1);
    INSERT INTO homepage_artwork_assignments VALUES ($id,1,$id,0);
    INSERT INTO artwork_section_assignments VALUES ($id,1,0);");
}
$tenant = new App\Platform\Tenancy\TenantContext(1,'uuid','test','Test','test.local','custom',true);
$repo = new App\Tenant\Artwork\ArtworkReadRepository($pdo);
function ids(array $rows): array { $ids=array_column($rows,'id'); sort($ids); return $ids; }
function check(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); }
foreach ([false,true] as $preview) {
    $expected=$preview?[1,2]:[1];
    check(ids($repo->publishedOrdered($tenant,50,'name',$preview))===$expected,'Portfolio visibility');
    check(ids($repo->latestPublished($tenant,50,$preview))===$expected,'Home visibility');
    foreach ([null,'all'] as $section) {
        $page=$repo->publishedPage($tenant,1,50,'name',$section,$preview);
        check(ids($page['items'])===$expected && $page['total']===count($expected),'Paged section/count visibility');
    }
    check($repo->findPublishedBySlug($tenant,'slug-3',$preview)===null,'Archived detail accessible');
}
$roles=new App\Http\Middleware\RequireTenantRoleBrowser(new App\Platform\Membership\MembershipRepository($pdo));
$placement=new App\Http\Controllers\Tenant\Admin\ArtworkPlacementController($roles,$pdo,new App\Support\Security\CsrfTokenService());
foreach (['homeOrderItems'=>[$tenant], 'sectionOrderItems'=>[$tenant,1]] as $method=>$args) {
    check(ids((new ReflectionMethod($placement,$method))->invokeArgs($placement,$args))===[1,2],'Archived order item');
}
$media=new App\Http\Controllers\Tenant\MediaController($pdo);
$find=new ReflectionMethod($media,'findMedia');
check($find->invoke($media,$tenant,'media-3',false,true)===null,'Archived preview media accessible');
check($find->invoke($media,$tenant,'media-2',false,true)!==null,'Draft preview media hidden');
check($find->invoke($media,$tenant,'media-3',false,false)!==null,'Admin archive media unavailable');
$pdo->exec('UPDATE artworks SET primary_media_id=3 WHERE id=1');
check($find->invoke($media,$tenant,'media-3',true,true)!==null,'Shared published media hidden');
echo "Archived artwork visibility checks passed.\n";

// Explicit homepage selections must not be silently truncated at twelve items.
for ($id=4; $id<=16; $id++) {
    $pdo->exec("INSERT INTO artworks (id,tenant_id,title,slug,status,sort_order) VALUES ($id,1,'Home $id','home-$id','published',$id);
    INSERT INTO artwork_type_assignments VALUES ($id,1);
    INSERT INTO homepage_artwork_assignments VALUES ($id,1,$id,$id);");
}
check(count($repo->latestPublished($tenant, null)) === 14, 'Explicit homepage selections truncated');
check(count($repo->latestPublished($tenant, 12)) === 12, 'Explicit repository limit not honored');
check(count($repo->latestPublished($tenant, null, true)) === 15, 'Preview selections truncated or archived artwork included');
echo "Homepage selection limit checks passed.\n";
