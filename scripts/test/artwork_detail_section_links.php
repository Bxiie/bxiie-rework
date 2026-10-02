<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec("CREATE TABLE artworks (id INTEGER, tenant_id INTEGER, status TEXT);
CREATE TABLE portfolio_sections (id INTEGER, tenant_id INTEGER, name TEXT, slug TEXT, status TEXT);
CREATE TABLE artwork_section_assignments (artwork_id INTEGER, section_id INTEGER);
INSERT INTO artworks VALUES (1,1,'published'),(2,2,'published');
INSERT INTO portfolio_sections VALUES (1,1,'Zebra','zebra','active'),(2,1,'Art & <Ink>','art ink','active'),(3,1,'Hidden','hidden','hidden'),(4,1,'Archived','archived','archived'),(5,2,'Other tenant','other','active');
INSERT INTO artwork_section_assignments VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(2,1);");
$tenant = new App\Platform\Tenancy\TenantContext(1,'uuid','test','Test','test.local','custom',true);
$c = new App\Http\Controllers\Tenant\HomeController(new App\Tenant\Settings\TenantSettingsRepository($pdo),new App\Tenant\Artwork\ArtworkReadRepository($pdo),$pdo);
$method = new ReflectionMethod($c,'artworkSectionLinks');
$expected = '<p class="artwork-section-links" aria-label="Portfolio sections"><a href="/portfolio?section=art%20ink">Art &amp; &lt;Ink&gt;</a> · <a href="/portfolio?section=zebra">Zebra</a></p>';
if ($method->invoke($c,$tenant,1) !== $expected) throw new RuntimeException('Incorrect section links, ordering, or escaping');
foreach ([2,999] as $id) if ($method->invoke($c,$tenant,$id) !== '') throw new RuntimeException('Unrelated artwork leaked');
$pdo->exec("UPDATE artworks SET status='archived' WHERE id=1");
if ($method->invoke($c,$tenant,1) !== '') throw new RuntimeException('Archived artwork links visible');
echo "Artwork detail section links checks passed.\n";
