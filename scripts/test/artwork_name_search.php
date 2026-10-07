<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/vendor/autoload.php';

function searchCheck(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
session_start();
final class SearchFixturePdo extends PDO {
    public function prepare(string $query, array $options=[]): PDOStatement|false {
        $query=str_replace([
            "GROUP_CONCAT(atype.code ORDER BY atype.code SEPARATOR ',')",
            "GROUP_CONCAT(ps.name ORDER BY LOWER(ps.name), ps.name SEPARATOR '||')",
        ],["GROUP_CONCAT(atype.code, ',')", "GROUP_CONCAT(ps.name, '||')"],$query);
        return parent::prepare($query,$options);
    }
}
$pdo = new SearchFixturePdo('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec("CREATE TABLE tenant_settings (tenant_id INTEGER, setting_key TEXT, setting_value TEXT);
INSERT INTO tenant_settings VALUES (1,'suppress_mailing_list_dialog','1');
CREATE TABLE portfolio_sections (id INTEGER, tenant_id INTEGER, name TEXT, slug TEXT, status TEXT, show_as_tab INTEGER, sort_order INTEGER);
INSERT INTO portfolio_sections VALUES (1,1,'Studies','studies','active',1,0),(2,2,'Other','other','active',1,0);
CREATE TABLE artworks (id INTEGER, tenant_id INTEGER, uuid TEXT, title TEXT, slug TEXT, medium TEXT, dimensions TEXT, year_created TEXT, status TEXT, sale_status TEXT, price INTEGER, is_one_off INTEGER, inventory_quantity INTEGER, primary_media_id INTEGER, sort_order INTEGER, created_at TEXT);
CREATE TABLE artwork_section_assignments (artwork_id INTEGER, section_id INTEGER, sort_order INTEGER);
CREATE TABLE artwork_type_assignments (artwork_id INTEGER, type_id INTEGER);
CREATE TABLE artwork_types (id INTEGER, code TEXT);
INSERT INTO artwork_types VALUES (1,'portfolio_images');
CREATE TABLE media_assets (id INTEGER, uuid TEXT, alt_text TEXT);");
$insert = $pdo->prepare('INSERT INTO artworks (id,tenant_id,title,status,sort_order,slug) VALUES (?,?,?,?,?,?)');
for ($id=1; $id<=25; $id++) {
    $insert->execute([$id, $id===25 ? 2 : 1, $id===24 ? '100%_! Study' : sprintf('Curves %02d', $id), $id===23 ? 'archived' : ($id===22 ? 'draft' : 'published'), $id, 'work-'.$id]);
    $pdo->exec("INSERT INTO artwork_type_assignments VALUES ($id,1); INSERT INTO artwork_section_assignments VALUES ($id," . ($id===25 ? 2 : 1) . ",$id)");
}
$tenant = new App\Platform\Tenancy\TenantContext(1,'uuid','test','Test','test.local','custom',true);
$repo = new App\Tenant\Artwork\ArtworkReadRepository($pdo);
$page = $repo->publishedPage($tenant,2,10,'name','studies',false,' curves ');
searchCheck($page['total']===21 && count($page['items'])===10 && $page['items'][0]['id']===11, 'Search must precede count, sort and pagination and exclude drafts/other tenants');
searchCheck($repo->publishedPage($tenant,1,10,'name','studies',true,'curves')['total']===22, 'Preview must include drafts but exclude archives');
searchCheck($repo->publishedPage($tenant,1,10,'name','other',false,'curves')['total']===0, 'Section tenant isolation');
searchCheck($repo->publishedPage($tenant,1,10,'name',null,false,'%_!')['total']===1, 'LIKE metacharacters must be literal');
searchCheck($repo->publishedPage($tenant,99,10,'name',null,false,'curves')['page']===3, 'Out-of-range page must clamp');
searchCheck($repo->publishedPage($tenant,1,100,'name',null,false,'')['page_size']===100, '100 per page must remain supported');
$controller = new App\Http\Controllers\Tenant\HomeController(new App\Tenant\Settings\TenantSettingsRepository($pdo),$repo,$pdo);
$_GET=['q'=>'Curves','section'=>'studies','per_page'=>10,'page'=>2,'sort'=>'name'];
$response=$controller->portfolio(new App\Http\Request('GET','/portfolio','test.local',$_GET,[]),$tenant);
$html=(new ReflectionProperty($response,'body'))->getValue($response);
$dom=new DOMDocument(); @$dom->loadHTML($html); $xpath=new DOMXPath($dom);
searchCheck($xpath->query('//input[@name="q" and @value="Curves"]')->length===1,'Rendered search control');
foreach ($xpath->query('//a[@data-artwork-page-link]') as $link) {
    parse_str(parse_url($link->getAttribute('href'),PHP_URL_QUERY) ?? '',$query);
    searchCheck(($query['per_page']??'')==='10','Page size lost');
    searchCheck(($query['sort']??'')==='name','Public sort lost while searching or clearing');
    if ($link->textContent==='Clear search') {
        searchCheck(!isset($query['q']) && ($query['section']??'')==='studies' && !isset($query['page']),'Clear must preserve section and reset page');
    } else searchCheck(($query['q']??'')==='Curves','Search lost in navigation');
}
$_GET=['q'=>'<not found>'];
$response=$controller->portfolio(new App\Http\Request('GET','/portfolio','test.local',$_GET,[]),$tenant);
$html=(new ReflectionProperty($response,'body'))->getValue($response);
searchCheck(str_contains($html,'No artworks match your search.') && str_contains($html,'&lt;not found&gt;'),'Escaped empty search response');
// Guard native PDO parameter binding: each searched field needs a distinct placeholder.
$admin=file_get_contents(dirname(__DIR__,2).'/app/Http/Controllers/Tenant/Admin/ArtworksController.php');
foreach (['q_title','q_medium','q_description'] as $name) searchCheck(substr_count($admin,':'.$name)===1,'Native PDO placeholder '.$name);
searchCheck(str_contains($admin,"unset(\$clearSearchQuery['q'], \$clearSearchQuery['page']);"),'Admin clear must retain filters');
$pdo->exec("ALTER TABLE artworks ADD COLUMN description TEXT;
ALTER TABLE artworks ADD COLUMN scheduled_publish_at TEXT;
ALTER TABLE media_assets ADD COLUMN storage_path TEXT;
ALTER TABLE media_assets ADD COLUMN mime_type TEXT;
ALTER TABLE media_assets ADD COLUMN width INTEGER;
ALTER TABLE media_assets ADD COLUMN height INTEGER;
CREATE TABLE roles (id INTEGER,slug TEXT,scope TEXT);
CREATE TABLE role_assignments (role_id INTEGER,user_id INTEGER,tenant_id INTEGER);
INSERT INTO roles VALUES (1,'tenant_owner','tenant');
INSERT INTO role_assignments VALUES (1,1,1);
UPDATE artworks SET sale_status='nfs',created_at='2026-01-01';");
$roles=new App\Http\Middleware\RequireTenantRoleBrowser(new App\Platform\Membership\MembershipRepository($pdo));
$adminController=new App\Http\Controllers\Tenant\Admin\ArtworksController($roles,$pdo,new App\Platform\Audit\AuditLogRepository($pdo),new App\Support\Security\CsrfTokenService());
$_GET=['q'=>'Curves','section_id'=>'1','status'=>'published','sort'=>'name','per_page'=>10,'page'=>2];
$response=$adminController->index(new App\Http\Request('GET','/admin/artworks','test.local',$_GET,[]),$tenant,['user_id'=>1]);
$html=(new ReflectionProperty($response,'body'))->getValue($response);
searchCheck(str_contains($html,'Showing 11–20 of 21') && str_contains($html,'Curves 11') && !str_contains($html,'Curves 01'),'Admin server search/count/page ordering');
@$dom->loadHTML($html); $xpath=new DOMXPath($dom);
foreach ($xpath->query('//a[@data-artwork-page-link]') as $link) {
    parse_str(parse_url($link->getAttribute('href'),PHP_URL_QUERY) ?? '',$query);
    searchCheck(($query['status']??'')==='published' && ($query['section_id']??'')==='1' && ($query['sort']??'')==='name' && ($query['per_page']??'')==='10','Admin filters lost');
    searchCheck($link->textContent==='Clear search' ? !isset($query['q']) : ($query['q']??'')==='Curves','Admin search navigation');
}
session_write_close();
echo "Artwork name search: query, visibility, literal matching, pagination and rendered navigation passed.\n";
