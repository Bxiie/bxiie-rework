<?php

declare(strict_types=1);
require dirname(__DIR__,2).'/vendor/autoload.php';
session_start();
// Exercise real controller/repository writes in an isolated database. Only MariaDB
// aggregate syntax and advisory-lock functions are adapted for SQLite here.
final class SocialFixturePdo extends PDO {
    public function prepare(string $query, array $options=[]): PDOStatement|false {
        $query=str_replace([
            'GET_LOCK(:lock_name, 5)', 'RELEASE_LOCK(:lock_name)', 'LEAST(available_at, CURRENT_TIMESTAMP)',
            'GROUP_CONCAT(ps.id ORDER BY ps.id)',
            "GROUP_CONCAT(ps.name ORDER BY LOWER(ps.name) SEPARATOR '||')",
        ],["CASE WHEN :lock_name <> '' THEN 1 ELSE 0 END", "CASE WHEN :lock_name <> '' THEN 1 ELSE 0 END", 'MIN(available_at, CURRENT_TIMESTAMP)', 'GROUP_CONCAT(ps.id)',"GROUP_CONCAT(ps.name, '||')"],$query);
        return parent::prepare($query,$options);
    }
}
$pdo=new SocialFixturePdo('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec("CREATE TABLE tenant_settings (tenant_id INTEGER, setting_key TEXT, setting_value TEXT);
CREATE TABLE artworks (id INTEGER, tenant_id INTEGER, title TEXT, slug TEXT, status TEXT, primary_media_id INTEGER);
INSERT INTO artworks VALUES (1,1,'Curves','curves','published',1);
CREATE TABLE media_assets (id INTEGER,uuid TEXT,storage_path TEXT,mime_type TEXT,width INTEGER,height INTEGER,alt_text TEXT,is_private INTEGER,tenant_id INTEGER);
INSERT INTO media_assets VALUES (1,'media-uuid','fixture.png','image/png',1200,300,'Curves',0,1);
CREATE TABLE artwork_section_assignments (artwork_id INTEGER,section_id INTEGER);
CREATE TABLE portfolio_sections (id INTEGER,tenant_id INTEGER,name TEXT);
CREATE TABLE social_templates (id INTEGER,tenant_id INTEGER,provider TEXT,is_default INTEGER,template_body TEXT,name TEXT);
INSERT INTO social_templates VALUES (1,1,'instagram',1,'{title}','Default');
CREATE TABLE social_connections (id INTEGER,tenant_id INTEGER,provider TEXT,is_default INTEGER,status TEXT,external_account_id TEXT,username TEXT);
INSERT INTO social_connections VALUES (1,1,'instagram',1,'active','test-account','test');
CREATE TABLE social_posts (id INTEGER PRIMARY KEY,uuid TEXT,tenant_id INTEGER,provider TEXT,social_connection_id INTEGER,source_artwork_id INTEGER,template_id INTEGER,caption TEXT,hashtags TEXT,snapshot_json TEXT,status TEXT,scheduled_at TEXT,next_attempt_at TEXT,created_by_user_id INTEGER,updated_by_user_id INTEGER,created_at TEXT,updated_at TEXT,remote_post_id TEXT,published_at TEXT);
CREATE TABLE social_post_items (id INTEGER PRIMARY KEY,social_post_id INTEGER,tenant_id INTEGER,artwork_id INTEGER,media_asset_id INTEGER,sort_order INTEGER,crop_mode TEXT,crop_json TEXT,created_at TEXT);
CREATE TABLE background_jobs (id INTEGER PRIMARY KEY,job_type TEXT,status TEXT,available_at TEXT,updated_at TEXT);
INSERT INTO background_jobs VALUES (1,'social.publish_due','queued','2099-01-01 00:00:00',CURRENT_TIMESTAMP);");
$controller=(new ReflectionClass(App\Http\Controllers\Tenant\SocialFrontController::class))->newInstanceWithoutConstructor();
$csrf=new App\Support\Security\CsrfTokenService();
foreach (['pdo'=>$pdo,'social'=>new App\Tenant\Social\SocialRepository($pdo),'settings'=>new App\Tenant\Settings\TenantSettingsRepository($pdo),'csrf'=>$csrf] as $key=>$value) (new ReflectionProperty($controller,$key))->setValue($controller,$value);
$tenant=new App\Platform\Tenancy\TenantContext(1,'uuid','test','Test','test.local','custom',true);
$submit=new ReflectionMethod($controller,'submitPost');
function requestCheck(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); }
$GLOBALS['artsfolio_user_timezone']='America/New_York';
$base=['csrf_token'=>$csrf->getOrCreate(),'artwork_id'=>1,'template_id'=>1,'caption'=>'Curves','media_artwork_ids'=>[1]];
foreach ([['post_now',''],['post_now','not a date'],['schedule','2099-07-01T12:00']] as [$action,$local]) {
    $_POST=$base+['publish_action'=>$action,'scheduled_local'=>$local];
    $before=time(); $response=$submit->invoke($controller,$tenant,['user_id'=>1]);
    requestCheck((new ReflectionProperty($response,'status'))->getValue($response)===303,'Valid submission did not redirect');
    $post=$pdo->query('SELECT * FROM social_posts ORDER BY id DESC LIMIT 1')->fetch();
    requestCheck($post['status']==='scheduled' && $post['social_connection_id']===1,'Queued post lost status/account');
    if ($action==='post_now') requestCheck(strtotime($post['scheduled_at'].' UTC')>=$before && strtotime($post['scheduled_at'].' UTC')<=time(),'Immediate post not due now');
    else requestCheck($post['scheduled_at']==='2099-07-01 16:00:00','Schedule not persisted in UTC');
    requestCheck($post['scheduled_at']===$post['next_attempt_at'],'Worker due time differs from schedule');
    requestCheck($pdo->query('SELECT COUNT(*) FROM social_post_items')->fetchColumn()===$post['id'],'Carousel item not stored');
}
requestCheck($pdo->query('SELECT available_at FROM background_jobs')->fetchColumn()<'2099','Immediate post failed to wake publisher');
foreach ([[],['publish_action'=>'schedule'],['publish_action'=>'unexpected'],['publish_action'=>'schedule','scheduled_local'=>'2099-11-01T01:30']] as $timing) {
    $_POST=$base+$timing; $response=$submit->invoke($controller,$tenant,['user_id'=>1]);
    requestCheck((new ReflectionProperty($response,'status'))->getValue($response)===422,'Invalid submission not rejected');
    requestCheck($pdo->query('SELECT COUNT(*) FROM social_posts')->fetchColumn()===3,'Invalid submission wrote a post');
}
$_GET=['artwork_id'=>1];
$response=(new ReflectionMethod($controller,'compose'))->invoke($controller,$tenant,['user_id'=>1]);
$html=(new ReflectionProperty($response,'body'))->getValue($response);
requestCheck(str_contains($html,'name="publish_action"') && str_contains($html,'America/New_York'),'Compose lost explicit mode or selected timezone');
if (in_array('--html',$argv,true)) echo $html;
else echo "Social controller requests: immediate/scheduled persistence, account binding, carousel, worker wake and invalid-request isolation passed.\n";
session_write_close();
