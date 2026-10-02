<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Admin\HomepageArtworksController;
use App\Http\Controllers\Tenant\HomeController;
use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Platform\Membership\MembershipRepository;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Artwork\ArtworkReadRepository;
use App\Tenant\Settings\TenantSettingsRepository;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

session_start();

function check(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

$tenant = new TenantContext(1, 'uuid', 'test', 'Test', 'test.local', 'custom', true);

// 1. extractHeroItem() is pure array logic: no hero id, hero not present,
//    and hero present must each behave correctly without touching the DB.
$homeController = new HomeController(
    new TenantSettingsRepository(new PDO('sqlite::memory:')),
    new ArtworkReadRepository(new PDO('sqlite::memory:')),
    new PDO('sqlite::memory:'),
);
$extract = new ReflectionMethod($homeController, 'extractHeroItem');

$items = [['id' => 1, 'title' => 'A'], ['id' => 2, 'title' => 'B'], ['id' => 3, 'title' => 'C']];
check($extract->invokeArgs($homeController, [&$items, 0]) === null, 'Hero id 0 must return null and leave items untouched');
check(count($items) === 3, 'Hero id 0 must not remove any item');

$items = [['id' => 1, 'title' => 'A'], ['id' => 2, 'title' => 'B'], ['id' => 3, 'title' => 'C']];
check($extract->invokeArgs($homeController, [&$items, 99]) === null, 'A hero id absent from items must return null');
check(count($items) === 3, 'A hero id absent from items must not remove anything');

$items = [['id' => 1, 'title' => 'A'], ['id' => 2, 'title' => 'B'], ['id' => 3, 'title' => 'C']];
$hero = $extract->invokeArgs($homeController, [&$items, 2]);
check($hero !== null && $hero['id'] === 2, 'The matching item must be returned as the hero');
check(count($items) === 2 && array_column($items, 'id') === [1, 3], 'The hero item must be removed from the remaining grid items');

// 2. homeHeroImageHtml() renders the large variant, a link to the artwork, a
//    caption, and the unpublished marker when relevant — pure string output,
//    no DB needed.
$render = new ReflectionMethod($homeController, 'homeHeroImageHtml');
$html = $render->invoke($homeController, [
    'title' => 'Big Painting',
    'slug' => 'big-painting',
    'medium' => 'Oil',
    'year_created' => '2026',
    'media_uuid' => 'abc-123',
    'media_alt_text' => 'Big Painting',
    'status' => 'published',
], false);
check(str_contains($html, 'class="home-hero-image"'), 'Hero markup missing its wrapper class');
check(str_contains($html, 'href="/artwork/big-painting"'), 'Hero markup missing the artwork link');
check(str_contains($html, 'variant=large'), 'Hero image must request the large media variant, not the thumb');
check(!str_contains($html, 'variant=thumb'), 'Hero image must not use the thumb variant');
check(str_contains($html, 'Big Painting'), 'Hero markup missing the title');
check(!str_contains($html, 'artwork-unpublished'), 'Published hero must not show the unpublished marker');

$draftHtml = $render->invoke($homeController, [
    'title' => 'Draft Piece', 'slug' => 'draft-piece', 'medium' => '', 'year_created' => '', 'media_uuid' => null, 'status' => 'draft',
], false);
check(str_contains($draftHtml, 'artwork-unpublished'), 'Unpublished hero must show the unpublished marker');

// 3. HomepageArtworksController persists/clears the hero selection correctly:
//    kept when selected+imaged, cleared when dropped from the selection or
//    when the chosen artwork has no primary image.
// Exercise real repository persistence in an isolated database. Translate
// only the MariaDB upsert syntax; queries, parameters and policy run unchanged.
final class HeroTestPdo extends Pdo\Sqlite
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'INSERT INTO tenant_settings')) {
            $query = preg_replace(
                '/ON DUPLICATE KEY UPDATE\s+setting_value = VALUES\(setting_value\),\s+updated_at = CURRENT_TIMESTAMP/',
                'ON CONFLICT(tenant_id, setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = CURRENT_TIMESTAMP',
                $query,
            );
        }

        return parent::prepare($query, $options);
    }
}

$pdo = new HeroTestPdo('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->createFunction('UTC_TIMESTAMP', static fn (): string => date('Y-m-d H:i:s'));
$pdo->exec(
    "CREATE TABLE roles (id INTEGER, slug TEXT, scope TEXT);
     CREATE TABLE role_assignments (role_id INTEGER, user_id INTEGER, tenant_id INTEGER);
     CREATE TABLE tenant_settings (tenant_id INTEGER, setting_key TEXT, setting_value TEXT, updated_at TEXT, PRIMARY KEY(tenant_id, setting_key));
     CREATE TABLE artworks (id INTEGER, tenant_id INTEGER, title TEXT, slug TEXT, status TEXT, sort_order INTEGER, primary_media_id INTEGER);
     CREATE TABLE artwork_types (id INTEGER, code TEXT);
     CREATE TABLE artwork_type_assignments (artwork_id INTEGER, type_id INTEGER);
     CREATE TABLE homepage_artwork_assignments (id INTEGER, tenant_id INTEGER, artwork_id INTEGER, sort_order INTEGER, created_at TEXT, updated_at TEXT);
     INSERT INTO roles VALUES (1,'tenant_owner','tenant');
     INSERT INTO role_assignments VALUES (1,1,1);
     INSERT INTO artwork_types VALUES (1,'portfolio_images');
     INSERT INTO artworks VALUES (1,1,'Imaged','imaged','published',0,10);
     INSERT INTO artworks VALUES (2,1,'Imageless','imageless','published',0,NULL);
     INSERT INTO artwork_type_assignments VALUES (1,1),(2,1);"
);

$roles = new RequireTenantRoleBrowser(new MembershipRepository($pdo));
$settings = new TenantSettingsRepository($pdo);
$owner = ['user_id' => 1];

function postHeroUpdate(HomepageArtworksController $controller, PDO $pdo, TenantContext $tenant, array $owner, CsrfTokenService $csrf, array $artworkIds, int $heroArtworkId): void
{
    $_POST = [
        'csrf_token' => $csrf->getOrCreate(),
        'artwork_ids' => $artworkIds,
        'hero_artwork_id' => (string) $heroArtworkId,
    ];
    $controller->update(new Request('POST', '/admin/portfolio-sections/home-page', 'test.local', [], []), $tenant, $owner);
    $_POST = [];
}

$csrf = new CsrfTokenService();
$controller = new HomepageArtworksController($roles, $pdo, $csrf, $settings);

// Selecting an imaged, selected artwork as hero must persist it.
postHeroUpdate($controller, $pdo, $tenant, $owner, $csrf, [1, 2], 1);
check($settings->get($tenant, 'home_hero_artwork_id', '') === '1', 'Hero selection on a valid imaged artwork was not saved');

// Re-saving without that artwork checked must clear the hero rather than
// leaving a dangling reference to an artwork no longer on the home page.
$settings->invalidate($tenant);
postHeroUpdate($controller, $pdo, $tenant, $owner, $csrf, [2], 1);
check($settings->get($tenant, 'home_hero_artwork_id', '') === '', 'Hero was not cleared after being dropped from the home-page selection');

// Picking an imageless artwork as hero (even if selected) must not be saved.
$settings->invalidate($tenant);
postHeroUpdate($controller, $pdo, $tenant, $owner, $csrf, [1, 2], 2);
check($settings->get($tenant, 'home_hero_artwork_id', '') === '', 'An imageless artwork must never be saved as the hero');

// Explicit "no hero" (0) clears any existing hero.
$settings->invalidate($tenant);
postHeroUpdate($controller, $pdo, $tenant, $owner, $csrf, [1, 2], 1);
check($settings->get($tenant, 'home_hero_artwork_id', '') === '1', 'Setup save for the "clear" step did not persist');
$settings->invalidate($tenant);
postHeroUpdate($controller, $pdo, $tenant, $owner, $csrf, [1, 2], 0);
check($settings->get($tenant, 'home_hero_artwork_id', '') === '', 'Choosing "No hero image" did not clear a previously saved hero');

// 4. index() marks the current hero radio checked, offers "no hero" when
//    unset, and disables the hero radio for an imageless artwork.
$settings->invalidate($tenant);
postHeroUpdate($controller, $pdo, $tenant, $owner, $csrf, [1, 2], 1);
$settings->invalidate($tenant);
$response = $controller->index(new Request('GET', '/admin/portfolio-sections/home-page', 'test.local', [], []), $tenant, $owner);
$bodyProperty = new ReflectionProperty($response, 'body');
$indexHtml = (string) $bodyProperty->getValue($response);
check(str_contains($indexHtml, 'name="hero_artwork_id" value="1" checked'), 'Index page did not mark the saved hero artwork checked');
check((bool) preg_match('/name="hero_artwork_id" value="2"[^>]*disabled/', $indexHtml), 'Index page did not disable the hero radio for an imageless artwork');

// 5. Static wiring checks for the public rendering side.
$homeControllerSource = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Http/Controllers/Tenant/HomeController.php');
foreach ([
    'reads the hero setting' => "\$this->settings->get(\$tenant, 'home_hero_artwork_id', '')",
    'extracts the hero before rendering the grid' => '$heroItem = $this->extractHeroItem($items, $heroArtworkId);',
    'renders the hero section' => 'if ($heroItem !== null) {',
] as $label => $needle) {
    check(str_contains($homeControllerSource, $needle), 'HomeController missing ' . $label);
}

$cssSource = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/site.css');
check(str_contains($cssSource, '.home-hero-image'), 'site.css is missing the .home-hero-image rules');

echo "Home hero image checks passed.\n";

// End of file.
