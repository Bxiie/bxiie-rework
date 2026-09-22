<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Http\Controllers\Tenant\Admin\SettingsController;
use App\Http\Controllers\Tenant\HomeController;
use App\Http\Controllers\Tenant\SalesController;
use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\View\TenantBranding;
use App\Platform\Membership\MembershipRepository;
use App\Platform\Settings\PlatformSettingsRepository;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Artwork\ArtworkReadRepository;
use App\Tenant\Sales\SalesRepository;
use App\Tenant\Settings\TenantSettingsRepository;

// Exercise real repository persistence in an isolated database. Translate only
// the MariaDB upsert syntax; queries, parameters and policy run unchanged.
final class BrandingTestPdo extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'INSERT INTO tenant_settings')) {
            $query = preg_replace('/ON DUPLICATE KEY UPDATE\s+setting_value = VALUES\(setting_value\),\s+updated_at = CURRENT_TIMESTAMP/',
                'ON CONFLICT(tenant_id, setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = CURRENT_TIMESTAMP', $query);
        }
        return parent::prepare($query, $options);
    }
}
$pdo = new BrandingTestPdo('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec("CREATE TABLE tenant_settings (tenant_id INTEGER, setting_key TEXT, setting_value TEXT, updated_at TEXT, PRIMARY KEY(tenant_id, setting_key));
CREATE TABLE tenants (id INTEGER PRIMARY KEY, complementary INTEGER DEFAULT 0);
INSERT INTO tenants VALUES(1,0),(2,0);
CREATE TABLE plans (id INTEGER PRIMARY KEY, slug TEXT, monthly_price_cents INTEGER);
CREATE TABLE tenant_plan_assignments (id INTEGER PRIMARY KEY, tenant_id INTEGER, plan_id INTEGER, status TEXT);
CREATE TABLE roles (id INTEGER, slug TEXT, scope TEXT);
CREATE TABLE role_assignments (role_id INTEGER, user_id INTEGER, tenant_id INTEGER);
INSERT INTO roles VALUES (1,'tenant_owner','tenant');
INSERT INTO role_assignments VALUES (1,1,1);
INSERT INTO plans VALUES (1,'studio',1200),(2,'free',0),(3,'fee',1200),(4,'starter',1200),(5,'custom-free',0),(6,'pro',2400);
INSERT INTO tenant_plan_assignments VALUES (1,1,1,'active'),(2,2,2,'active');");
$tenant = new TenantContext(1, 'uuid-1', 'test', 'Test', 'test.local', 'custom', true);
$other = new TenantContext(2, 'uuid-2', 'other', 'Other', 'other.local', 'custom', true);
$key = TenantSettingsRepository::ARTSFOLIO_BRANDING;
$settings = new TenantSettingsRepository($pdo);
$count = 0;
function check(bool $passed, string $message): void {
    global $count;
    if (!$passed) throw new RuntimeException($message);
    $count++;
}
check($settings->get($tenant, $key) === '1', 'Existing/new tenants default ON without backfill');
foreach ([null, '', 'false', 'invalid', '1'] as $raw) {
    $settings->set($tenant, $key, $raw);
    check($settings->get($tenant, $key) === '1', 'Only explicit zero disables');
}
$settings->set($tenant, $key, '0');
check($settings->get($tenant, $key) === '0', 'Eligible tenant can disable');
check((new TenantSettingsRepository($pdo))->get($tenant, $key) === '0', 'Preference persists across requests');
check($settings->get($other, $key) === '1', 'Tenant isolation');
check(TenantBranding::render($tenant, $settings) === '', 'Disabled branding is absent from HTML');
foreach ([2,3,4,5] as $planId) {
    $pdo->exec("UPDATE tenant_plan_assignments SET plan_id=$planId WHERE tenant_id=1");
    check($settings->get($tenant, $key) === '1', 'Downgrade overrides stored OFF');
    $settings->set($tenant, $key, '0');
    check($pdo->query("SELECT setting_value FROM tenant_settings WHERE tenant_id=1 AND setting_key='$key'")->fetchColumn() === '1', 'Ineligible disable is normalized at persistence boundary');
}
$pdo->exec("UPDATE tenant_plan_assignments SET plan_id=6 WHERE tenant_id=1");
foreach (['trial','active','manual'] as $status) {
    $pdo->exec("UPDATE tenant_plan_assignments SET status='$status' WHERE tenant_id=1");
    check($settings->canDisableArtsfolioBranding($tenant), 'Assigned paid tier is eligible');
}
$pdo->exec("UPDATE tenant_plan_assignments SET status='canceled' WHERE tenant_id=1");
check(!$settings->canDisableArtsfolioBranding($tenant), 'Canceled assignment cannot disable');
$pdo->exec('DELETE FROM tenant_plan_assignments WHERE tenant_id=1');
$settings->set($tenant, 'billing_plan', 'pro');
check(!$settings->canDisableArtsfolioBranding($tenant), 'Legacy editable plan setting cannot grant entitlement');
$pdo->exec("INSERT INTO tenant_plan_assignments VALUES(3,1,1,'active')");

session_start();
$csrf = new CsrfTokenService();
$token = $csrf->getOrCreate();
$controller = new SettingsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $settings, $csrf);
$request = new Request('POST', '/admin/settings', 'test.local', [], []);
$user = ['user_id' => 1];
$_POST = ['csrf_token' => $token, 'settings_section' => 'identity', 'branding_setting_present' => '1'];
$controller->update($request, $tenant, $user);
check($settings->get($tenant, $key) === '0', 'Unchecked eligible admin checkbox persists OFF');
$_POST = ['csrf_token' => $token, 'settings_section' => 'miscellaneous'];
$controller->update($request, $tenant, $user);
check($settings->get($tenant, $key) === '0', 'Unrelated section preserves OFF');
$_POST = ['csrf_token' => $token, 'settings_section' => 'identity', 'branding_setting_present' => '1'];
$_POST['show_artsfolio_branding'] = '1';
$controller->update($request, $tenant, $user);
check($settings->get($tenant, $key) === '1', 'Checked admin checkbox persists ON');
unset($_POST['show_artsfolio_branding'], $_POST['branding_setting_present']);
$controller->update($request, $tenant, $user);
check($settings->get($tenant, $key) === '1', 'Legacy form omission preserves preference');
$pdo->exec('UPDATE tenant_plan_assignments SET plan_id=2 WHERE tenant_id=1');
$_POST['branding_setting_present'] = '1';
$controller->update($request, $tenant, $user);
check($settings->get($tenant, $key) === '1', 'Forged free-plan POST cannot disable');
$pdo->exec('UPDATE tenant_plan_assignments SET plan_id=1 WHERE tenant_id=1');
$_POST['csrf_token'] = 'forged';
$controller->update($request, $tenant, $user);
check($settings->get($tenant, $key) === '1', 'Invalid CSRF cannot change preference');
$_POST['csrf_token'] = $token;
$controller->update($request, $tenant, null);
check($settings->get($tenant, $key) === '1', 'Unauthenticated update denied');

// Render the actual shared layouts used for portfolio/detail/about/contact and
// cart/checkout success/failure pages, asserting footer placement and link target.
$settings->set($tenant, 'suppress_mailing_list_dialog', '1');
$home = new HomeController($settings, new ArtworkReadRepository($pdo), $pdo);
$sales = new SalesController(new SalesRepository($pdo), $settings, new PlatformSettingsRepository($pdo), $csrf, $pdo);
foreach ([[$home, 'layout'], [$sales, 'tenantPage']] as [$instance, $method]) {
    $render = new ReflectionMethod($instance, $method);
    $settings->set($tenant, $key, '1');
    $html = $render->invoke($instance, $tenant, 'Page', '<h1>Content</h1>');
    check(preg_match('#<footer\b[^>]*>.*Created with ArtsFolio.*</footer>#s', $html) === 1, "$method branding in footer");
    check(substr_count($html, 'Created with ArtsFolio</a>') === 1, "$method exactly one attribution");
    check(str_contains($html, 'href="https://artsfol.io/" target="_blank" rel="noopener noreferrer"'), "$method safe new-window platform link");
    $settings->set($tenant, $key, '0');
    check(!str_contains($render->invoke($instance, $tenant, 'Page', ''), 'tenant-powered-by'), "$method honors eligible OFF");
    $pdo->exec('UPDATE tenant_plan_assignments SET plan_id=2 WHERE tenant_id=1');
    check(str_contains($render->invoke($instance, $tenant, 'Page', ''), 'tenant-powered-by'), "$method enforces downgrade");
    $pdo->exec('UPDATE tenant_plan_assignments SET plan_id=1 WHERE tenant_id=1');
}
// Complementary is a platform-controlled tenant flag, including free tiers.
$pdo->exec('UPDATE tenant_plan_assignments SET plan_id=2 WHERE tenant_id=1');
$pdo->exec('UPDATE tenants SET complementary=1 WHERE id=1');
check($settings->canDisableArtsfolioBranding($tenant), 'Complementary free tenant is eligible');
$_POST = ['csrf_token' => $token, 'settings_section' => 'identity', 'branding_setting_present' => '1'];
$controller->update($request, $tenant, $user);
check((new TenantSettingsRepository($pdo))->get($tenant, $key) === '0', 'Complementary OFF persists through admin save');
check(TenantBranding::render($tenant, $settings) === '', 'Complementary OFF hides footer');
check(!$settings->canDisableArtsfolioBranding($other), 'Complementary entitlement is tenant-scoped');
ob_start();
$controller->edit($request, $tenant, $user)->send();
$adminHtml = ob_get_clean();
check(preg_match('/name="show_artsfolio_branding" value="1">/', $adminHtml) === 1, 'Complementary admin toggle enabled and unchecked');
check(!str_contains($adminHtml, 'tenant-powered-by'), 'No public attribution in admin footer');
$pdo->exec('UPDATE tenants SET complementary=0 WHERE id=1');
check($settings->get($tenant, $key) === '1', 'Revoking complementary restores required branding');
ob_start();
$controller->edit($request, $tenant, $user)->send();
$adminHtml = ob_get_clean();
check(str_contains($adminHtml, 'name="show_artsfolio_branding" value="1" checked disabled'), 'Free-plan admin toggle locked ON');
$pdo->exec('DROP TABLE tenant_plan_assignments');
check(!$settings->canDisableArtsfolioBranding($tenant), 'Missing billing schema fails closed');
echo "Tenant footer branding: $count checks passed.\n";
