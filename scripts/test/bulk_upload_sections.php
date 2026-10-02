<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec("CREATE TABLE artworks (id INTEGER, tenant_id INTEGER);
CREATE TABLE portfolio_sections (id INTEGER PRIMARY KEY, uuid TEXT, tenant_id INTEGER, name TEXT, slug TEXT, show_as_tab INTEGER, sort_order INTEGER, status TEXT, created_at TEXT, updated_at TEXT, UNIQUE(tenant_id,slug));
CREATE TABLE artwork_section_assignments (artwork_id INTEGER,section_id INTEGER,created_at TEXT, UNIQUE(artwork_id,section_id));
INSERT INTO artworks VALUES (1,1),(2,1),(3,2);
INSERT INTO portfolio_sections (id,tenant_id,name,slug,status) VALUES (1,1,'Paintings','paintings','active'),(2,2,'New Work','new-work','active'),(3,1,'Other','new-work','active');");
$reflection=new ReflectionClass(App\Http\Controllers\Tenant\Admin\ArtworkBulkUploadController::class);
$c=$reflection->newInstanceWithoutConstructor();$reflection->getProperty('pdo')->setValue($c,$pdo);
$method=$reflection->getMethod('assignSections');
$created=$method->invoke($c,1,1,' Paintings | New Work | paintings || 0 |');
if (array_values($created)!==['New Work','0']) throw new RuntimeException('Created section reporting incorrect');
$rows=$pdo->query('SELECT ps.name,ps.slug FROM portfolio_sections ps JOIN artwork_section_assignments asa ON asa.section_id=ps.id WHERE asa.artwork_id=1 ORDER BY ps.id')->fetchAll();
if($rows!==[['name'=>'Paintings','slug'=>'paintings'],['name'=>'New Work','slug'=>'new-work-2'],['name'=>'0','slug'=>'0']])throw new RuntimeException('Section parsing, creation, tenant isolation or slug collision failed');
if ($method->invoke($c,1,2,'NEW WORK|Paintings')!==[]) throw new RuntimeException('Existing sections reported as new');
if((int)$pdo->query('SELECT COUNT(*) FROM portfolio_sections')->fetchColumn()!==5)throw new RuntimeException('Repeated row created duplicate section');
$failed=false;try{$method->invoke($c,1,3,'Forbidden');}catch(InvalidArgumentException){$failed=true;}
if(!$failed || (int)$pdo->query('SELECT COUNT(*) FROM portfolio_sections')->fetchColumn()!==5)throw new RuntimeException('Tenant boundary failed');
echo "Bulk upload section checks passed.\n";

$summary=$reflection->getMethod('importSummary');
$html=$summary->invoke($c,3,[1=>'Zebra',2=>'Art & <Ink>']);
if (!str_contains($html,'3 image(s) uploaded.') || !str_contains($html,'Sections created (2)') || !str_contains($html,'<li>Art &amp; &lt;Ink&gt;</li><li>Zebra</li>')) throw new RuntimeException('Import summary incorrect');
if (!str_contains($summary->invoke($c,0,[]),'No new sections were created.')) throw new RuntimeException('Empty summary incorrect');
echo "Bulk upload result summary checks passed.\n";
