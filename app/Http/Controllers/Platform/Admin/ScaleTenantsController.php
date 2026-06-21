<?php

/**
 * Platform scale fixture administration controller.
 */

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Http\View\ErrorPage;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Membership\Roles;
use App\Platform\ScaleTesting\ScaleTenantFixtureService;
use App\Support\Flash\FlashMessages;
use App\Support\Security\CsrfTokenService;
use Throwable;

/**
 * Provides platform-admin controls for synthetic 1000-tenant scale fixtures.
 */
final class ScaleTenantsController
{
    public function __construct(
        private readonly RequirePlatformRole $roles,
        private readonly ScaleTenantFixtureService $fixtures,
        private readonly CsrfTokenService $csrf,
        private readonly ?AuditLogRepository $auditLog = null,
    ) {
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $summary = $this->fixtures->summary();
        $csrf = AdminLayout::escape($this->csrf->getOrCreate());
        $tenantCount = AdminLayout::escape((string) $summary['tenants']);
        $artworkCount = AdminLayout::escape((string) $summary['artworks']);
        $mediaCount = AdminLayout::escape((string) $summary['media_assets']);
        $eventCount = AdminLayout::escape((string) $summary['analytics_events']);
        $markerKey = AdminLayout::escape((string) $summary['marker_key']);
        $markerValue = AdminLayout::escape((string) $summary['marker_value']);
        $slugPrefix = AdminLayout::escape((string) $summary['slug_prefix']);

        $body = <<<HTML
<p class="admin-muted">Create or remove synthetic scale-test tenants from platform admin. These fixtures are deliberately isolated from real tenants by both slug prefix and marker setting.</p>
<section class="admin-panel">
    <h2>Current scale fixtures</h2>
    <table class="admin-table">
        <tbody>
            <tr><th>Tenant marker</th><td><code>{$markerKey}</code> = <code>{$markerValue}</code></td></tr>
            <tr><th>Slug prefix</th><td><code>{$slugPrefix}</code></td></tr>
            <tr><th>Scale tenants</th><td>{$tenantCount}</td></tr>
            <tr><th>Scale artworks</th><td>{$artworkCount}</td></tr>
            <tr><th>Scale media assets</th><td>{$mediaCount}</td></tr>
            <tr><th>Scale analytics events</th><td>{$eventCount}</td></tr>
        </tbody>
    </table>
</section>
<section class="admin-panel">
    <h2>Create or reset scale tenants</h2>
    <p class="admin-muted">Use <strong>Reset</strong> for a clean 1000-tenant test dataset. Reset first removes existing marked scale fixtures, then recreates them.</p>
    <form method="post" action="/platform/admin/scale-tenants/create" class="admin-stacked-form confirm-scale-fixture-form" data-confirm="Type create scale tenants to seed synthetic scale data.">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <label>Tenants <input type="number" name="tenants" value="1000" min="1" max="5000"></label>
        <label>Artworks per tenant <input type="number" name="artworks_per_tenant" value="50" min="0" max="500"></label>
        <label>Analytics events per tenant <input type="number" name="events_per_tenant" value="200" min="0" max="5000"></label>
        <label>Action
            <select name="scale_action">
                <option value="reset">Reset existing scale fixtures, then seed</option>
                <option value="seed">Seed or update without cleanup</option>
            </select>
        </label>
        <label>Confirmation <input type="text" name="confirmation" required placeholder="create scale tenants"></label>
        <button type="submit">Create scale tenants</button>
    </form>
</section>
<section class="admin-panel admin-danger-zone">
    <h2>Remove scale tenants</h2>
    <p class="admin-muted">Cleanup removes only tenants whose slug starts with <code>{$slugPrefix}</code> and whose tenant settings contain the exact marker above.</p>
    <form method="post" action="/platform/admin/scale-tenants/remove" class="admin-stacked-form confirm-scale-fixture-form" data-confirm="Type remove scale tenants to delete only marked synthetic tenants.">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <label>Confirmation <input type="text" name="confirmation" required placeholder="remove scale tenants"></label>
        <button type="submit">Remove scale tenants</button>
    </form>
</section>
<script>
document.querySelectorAll('.confirm-scale-fixture-form').forEach(function(form) {
    form.addEventListener('submit', function(event) {
        var expected = form.querySelector('input[name="confirmation"]').getAttribute('placeholder') || '';
        var actual = form.querySelector('input[name="confirmation"]').value || '';
        if (actual.toLowerCase() !== expected.toLowerCase()) {
            alert('Confirmation text must be exactly: ' + expected);
            event.preventDefault();
        }
    });
});
</script>
HTML;

        return Response::html(AdminLayout::render(title: 'Scale Tenants', active: 'scale', body: $body));
    }

    public function create(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }
        if (strtolower(trim((string) ($_POST['confirmation'] ?? ''))) !== 'create scale tenants') {
            return Response::html('<h1>Scale tenant confirmation required</h1>', 422);
        }

        $tenantCount = $this->boundedInt($_POST['tenants'] ?? 1000, 1, 5000, 1000);
        $artworksPerTenant = $this->boundedInt($_POST['artworks_per_tenant'] ?? 50, 0, 500, 50);
        $eventsPerTenant = $this->boundedInt($_POST['events_per_tenant'] ?? 200, 0, 5000, 200);
        $action = (string) ($_POST['scale_action'] ?? 'reset');

        try {
            if ($action === 'reset') {
                $this->fixtures->cleanup();
            } elseif ($action !== 'seed') {
                return Response::html('<h1>Invalid scale fixture action</h1>', 422);
            }

            $summary = $this->fixtures->seed($tenantCount, $artworksPerTenant, $eventsPerTenant);
            $this->auditLog?->record('platform.scale_tenants.created', null, (int) ($currentUser['user_id'] ?? 0), 'scale-fixtures', 'scale-tenants', [
                'action' => $action,
                'tenants' => $tenantCount,
                'artworks_per_tenant' => $artworksPerTenant,
                'events_per_tenant' => $eventsPerTenant,
                'summary' => $summary,
            ], $request->server('REMOTE_ADDR'));
            FlashMessages::success('Scale tenant fixtures created.');
        } catch (Throwable $e) {
            FlashMessages::error('Scale tenant creation failed: ' . $e->getMessage());
        }

        return new Response('', 303, ['Location' => '/platform/admin/scale-tenants']);
    }

    public function remove(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }
        if (strtolower(trim((string) ($_POST['confirmation'] ?? ''))) !== 'remove scale tenants') {
            return Response::html('<h1>Scale tenant removal confirmation required</h1>', 422);
        }

        try {
            $summary = $this->fixtures->cleanup();
            $this->auditLog?->record('platform.scale_tenants.removed', null, (int) ($currentUser['user_id'] ?? 0), 'scale-fixtures', 'scale-tenants', $summary, $request->server('REMOTE_ADDR'));
            FlashMessages::success('Scale tenant fixtures removed.');
        } catch (Throwable $e) {
            FlashMessages::error('Scale tenant removal failed: ' . $e->getMessage());
        }

        return new Response('', 303, ['Location' => '/platform/admin/scale-tenants']);
    }

    private function canManage(?array $currentUser): bool
    {
        return $this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN]);
    }

    private function boundedInt(mixed $value, int $min, int $max, int $default): int
    {
        $string = trim((string) $value);
        if (!preg_match('/^\d+$/', $string)) {
            return $default;
        }

        return max($min, min($max, (int) $string));
    }
}

// End of file.
