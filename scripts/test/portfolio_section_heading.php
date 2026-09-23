<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/vendor/autoload.php';

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec("CREATE TABLE tenant_settings (tenant_id INTEGER, setting_key TEXT, setting_value TEXT);
INSERT INTO tenant_settings VALUES (1,'suppress_mailing_list_dialog','1');
CREATE TABLE portfolio_sections (id INTEGER, tenant_id INTEGER, name TEXT, slug TEXT, status TEXT, show_as_tab INTEGER, sort_order INTEGER);
INSERT INTO portfolio_sections VALUES (1,1,'Art & <Ink>','ink','active',0,0),(2,1,'Archived','archived','archived',1,0),(3,2,'Private other tenant','other','active',1,0);
CREATE TABLE artworks (id INTEGER, tenant_id INTEGER, uuid TEXT, title TEXT, slug TEXT, medium TEXT, dimensions TEXT, year_created TEXT, status TEXT, sale_status TEXT, price INTEGER, is_one_off INTEGER, inventory_quantity INTEGER, primary_media_id INTEGER, sort_order INTEGER, created_at TEXT);
CREATE TABLE artwork_section_assignments (artwork_id INTEGER, section_id INTEGER, sort_order INTEGER);
CREATE TABLE artwork_type_assignments (artwork_id INTEGER, type_id INTEGER);
CREATE TABLE artwork_types (id INTEGER, code TEXT);
CREATE TABLE media_assets (id INTEGER, uuid TEXT, alt_text TEXT);");
$tenant = new App\Platform\Tenancy\TenantContext(1,'uuid','test','Test','test.local','custom',true);
$controller = new App\Http\Controllers\Tenant\HomeController(new App\Tenant\Settings\TenantSettingsRepository($pdo),new App\Tenant\Artwork\ArtworkReadRepository($pdo),$pdo);
$count = 0;
foreach (['ink', '', 'archived', 'other', 'missing'] as $slug) {
    $_GET = ['section' => $slug, 'page' => 2];
    $response = $controller->portfolio(new App\Http\Request('GET','/portfolio','test.local',$_GET,[]),$tenant);
    $html = (new ReflectionProperty($response,'body'))->getValue($response);
    if ($slug === 'ink') {
        if (!preg_match('#data-artwork-pager-root.*<h2 data-portfolio-section-heading[^>]*>Art &amp; &lt;Ink&gt;</h2>.*No published artwork#s', $html)) {
            throw new RuntimeException('Selected section heading must be escaped and inside the replaceable grid region, including empty/direct-link sections');
        }
    } elseif (str_contains($html, 'data-portfolio-section-heading')) {
        throw new RuntimeException('All, unknown, archived, or other tenant section must not display a heading');
    }
    $count++;
}
echo "Portfolio section heading: $count checks passed.\n";
