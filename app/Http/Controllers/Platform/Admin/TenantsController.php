<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Http\View\ErrorPage;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Identity\AdminUserRepository;
use App\Platform\Identity\PasswordHasher;
use App\Platform\Membership\Roles;
use App\Platform\Tenants\TenantAdminRepository;
use App\Support\Flash\FlashMessages;
use App\Support\Security\CsrfTokenService;

/**
 * Handles platform-admin tenant list and tenant drill-in screens.
 */
final class TenantsController
{
    public function __construct(
        private readonly RequirePlatformRole $roles,
        private readonly TenantAdminRepository $tenants,
        private readonly ?AdminUserRepository $users = null,
        private readonly ?PasswordHasher $hasher = null,
        private readonly ?CsrfTokenService $csrf = null,
        private readonly ?AuditLogRepository $auditLog = null,
    ) {
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->canViewTenants($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $tenantSearchRaw = trim((string) ($_GET['q'] ?? ''));
        $tenantSearchValue = $this->escape($tenantSearchRaw);
        $tenantRows = $tenantSearchRaw !== '' ? $this->searchTenants($tenantSearchRaw) : $this->tenants->latest();

        $rows = '';
        foreach ($tenantRows as $tenant) {
            $id = (int) $tenant['id'];
            $rows .= '<tr>'
                . '<td>' . $this->escape((string) $id) . '</td>'
                . '<td><a href="/platform/admin/tenants/' . $id . '">' . $this->escape((string) $tenant['slug']) . '</a></td>'
                . '<td>' . $this->escape((string) $tenant['name']) . '</td>'
                . '<td>' . $this->escape((string) $tenant['status']) . '</td>'
                . '<td>' . $this->escape((string) $tenant['domain_count']) . '</td>'
                . '<td>' . $this->escape((string) $tenant['created_at']) . '</td>'
                . '<td>' . ($this->csrf ? $this->tenantStatusActions($id, (string) $tenant['status'], $this->escape($this->csrf->getOrCreate())) : '') . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = $tenantSearchRaw !== ''
                ? '<tr><td colspan="7">No tenants matched your search.</td></tr>'
                : '<tr><td colspan="7">No tenants found.</td></tr>';
        }

        return Response::html(AdminLayout::render(
            title: 'Tenants',
            active: 'tenants',
            body: <<<HTML
<form class="platform-tenant-search admin-card" method="get" action="/platform/admin/tenants" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;">
    <label style="display:flex;flex-direction:column;gap:.25rem;"><span>Search tenants</span><input type="search" name="q" value="{$tenantSearchValue}" placeholder="Slug, name, status, domain, plan, or billing" autocomplete="off"></label>
    <button type="submit">Search</button>
    <a href="/platform/admin/tenants">Clear</a>
    <span class="admin-muted">Searches all tenants. Results are capped at 250.</span>
</form>
<p class="admin-muted">Open a tenant to review tenant users, email addresses, names, roles, membership status, and last log on timestamps.</p>


<table class="admin-table">
    <thead><tr><th>ID</th><th>Slug</th><th>Name</th><th>Status</th><th>Domains</th><th>Created</th><th>Actions</th></tr></thead>
    <tbody>{$rows}</tbody>
</table>
<script>document.querySelectorAll('.confirm-platform-tenant-action').forEach(function(form){form.addEventListener('submit',function(event){var action=form.getAttribute('data-action')||'update';if(!confirm('Confirm '+action+' for this tenant. Suspended tenant resources will show the ArtsFolio unavailable page.')){event.preventDefault();}});});</script>
HTML,
        ));
    }

    public function show(Request $request, ?array $currentUser, int $tenantId): Response
    {
        if (!$this->canViewTenants($currentUser) || !$this->users || !$this->csrf) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $tenant = $this->findTenant($tenantId);
        if (!$tenant) {
            return Response::html(ErrorPage::notFound('Tenant not found.'), 404);
        }

        $csrf = $this->escape($this->csrf->getOrCreate());
        $tenantName = $this->escape((string) $tenant['name']);
        $tenantSlug = $this->escape((string) $tenant['slug']);
        $tenantUrl = $this->tenants->publicUrlForTenant($tenantId);
        $tenantLink = $tenantUrl !== null
            ? '<p><a class="button secondary" target="_blank" rel="noopener" href="' . $this->escape($tenantUrl) . '">Open tenant site in new tab</a></p>'
            : '<p class="admin-muted">No active tenant subdomain is currently available.</p>';
        $billingDetails = $this->tenantBillingDetails($tenantId);
        $noticeCode = (string) ($_GET['notice'] ?? '');
        $noticeText = match ($noticeCode) {
            'complementary-updated' => 'Tenant billing override updated.',
            'password-updated' => 'Tenant user password updated.',
            'onboarding-reset' => 'Tenant onboarding state reset.',
            default => '',
        };
        $notice = $noticeText !== '' ? '<p class="admin-notice admin-notice-success">' . $this->escape($noticeText) . '</p>' : '';
        $rows = '';

        foreach ($this->users->tenantUsers($tenantId) as $user) {
            $id = (int) $user['id'];
            $email = $this->escape((string) $user['email']);
            $name = $this->escape((string) ($user['display_name'] ?? ''));
            $status = $this->escape((string) ($user['membership_status'] ?? ''));
            $roles = $this->escape((string) ($user['roles'] ?? ''));
            $lastLogin = $this->escape((string) ($user['last_login_at'] ?? 'Never'));
            $rows .= <<<HTML
<tr>
    <td>{$id}</td><td><strong>{$email}</strong><br><small>{$name}</small></td><td>{$status}</td><td>{$roles}</td><td>{$lastLogin}</td>
    <td><form method="post" action="/platform/admin/tenants/users/password" class="admin-inline-form"><input type="hidden" name="csrf_token" value="{$csrf}"><input type="hidden" name="tenant_id" value="{$tenantId}"><input type="hidden" name="user_id" value="{$id}"><input type="password" name="new_password" minlength="12" required placeholder="New password"><button type="submit">Change password</button></form></td>
</tr>
HTML;
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6">No tenant users found.</td></tr>';
        }

        return Response::html(AdminLayout::render(
            title: "Tenant: {$tenantName}",
            active: 'tenants',
            body: <<<HTML
<p><a href="/platform/admin/tenants">&larr; Tenants</a></p>
{$notice}
<p><strong>Slug:</strong> {$tenantSlug}</p>
{$tenantLink}

<section class="admin-panel admin-billing-override-panel">
    <div class="admin-panel-heading">
        <div>
            <h2>Billing override</h2>
            <p class="admin-muted">Waives the monthly platform subscription only. Sales economics still apply.</p>
        </div>
    </div>
    <form method="post" action="/platform/admin/tenants/complementary" class="admin-stacked-form">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <input type="hidden" name="tenant_id" value="{$tenantId}">
        <label class="admin-checkbox-card">
            <input type="checkbox" name="complementary" value="1"{$this->complementaryChecked($tenantId)}>
            <span><strong>Complementary tenant</strong><small>No monthly platform service billing. Tenant still pays platform commission and credit-card charges on sales.</small></span>
        </label>
        <div><button type="submit">Save billing override</button></div>
    </form>
</section>

<section class="admin-panel">
    <h2>Onboarding</h2>
    <p class="admin-muted">Reset the tenant-wide dashboard checklist and guided-tour state without changing site content, artwork, users, branding, or billing.</p>
    <form method="post" action="/platform/admin/tenants/onboarding/reset" onsubmit="return confirm('Reset onboarding for this tenant? The tenant dashboard checklist and guided tour will appear as they do for a newly created tenant.');">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <input type="hidden" name="tenant_id" value="{$tenantId}">
        <button type="submit">Reset onboarding</button>
    </form>
</section>
{$billingDetails}
<table class="admin-table">
    <thead><tr><th>ID</th><th>User</th><th>Membership</th><th>Roles</th><th>Last log on</th><th>Password</th></tr></thead>
    <tbody>{$rows}</tbody>
</table>
HTML,
        ));
    }

    public function updateComplementary(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManageTenants($currentUser) || !$this->csrf) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }
        $tenantId = (int) ($_POST['tenant_id'] ?? 0);
        if ($tenantId < 1) {
            return Response::html('<h1>Invalid tenant billing override request</h1>', 422);
        }
        $enabled = isset($_POST['complementary']) ? 1 : 0;
        $stmt = $this->pdo()->prepare('UPDATE tenants SET complementary = :enabled, updated_at = CURRENT_TIMESTAMP WHERE id = :tenant_id');
        $stmt->execute(['enabled' => $enabled, 'tenant_id' => $tenantId]);
        $this->auditLog?->record('platform.tenant.complementary_updated', null, (int) ($currentUser['user_id'] ?? 0), 'tenant', (string) $tenantId, ['complementary' => $enabled], $request->server('REMOTE_ADDR'));
        FlashMessages::success('Tenant billing override updated.');
        return new Response('', 303, ['Location' => '/platform/admin/tenants/' . $tenantId . '?notice=complementary-updated']);
    }


    /**
     * Resets tenant-wide onboarding state from the platform tenant page.
     */
    public function resetOnboarding(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManageTenants($currentUser) || !$this->csrf) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }

        $tenantId = (int) ($_POST['tenant_id'] ?? 0);
        if ($tenantId < 1 || !$this->findTenant($tenantId)) {
            return Response::html('<h1>Invalid tenant onboarding reset request</h1>', 422);
        }

        $stmt = $this->pdo()->prepare(
            "DELETE FROM tenant_settings
             WHERE tenant_id = :tenant_id
               AND (
                    setting_key LIKE 'onboarding\\_%'
                 OR setting_key LIKE 'admin\\_onboarding\\_%'
                 OR setting_key LIKE 'admin\\_tour\\_%'
                 OR setting_key LIKE 'getting\\_started\\_%'
                 OR setting_key LIKE 'dashboard\\_checklist\\_%'
               )"
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        $this->auditLog?->record(
            'platform.tenant.onboarding_reset',
            $tenantId,
            isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null,
            'tenant',
            (string) $tenantId,
            ['deleted_setting_count' => $stmt->rowCount(), 'source' => 'platform_admin'],
            $request->server('REMOTE_ADDR'),
        );

        FlashMessages::success('Tenant onboarding state reset.');
        return new Response('', 303, ['Location' => '/platform/admin/tenants/' . $tenantId . '?notice=onboarding-reset']);
    }


    private function tenantBillingDetails(int $tenantId): string
    {
        try {
            $stmt = $this->pdo()->prepare('SELECT tpa.*, p.name AS plan_name, p.slug AS plan_slug, p.monthly_price_cents FROM tenant_plan_assignments tpa JOIN plans p ON p.id = tpa.plan_id WHERE tpa.tenant_id = :tenant_id ORDER BY tpa.id DESC LIMIT 1');
            $stmt->execute(['tenant_id' => $tenantId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable) { $row = false; }
        if (!$row) { return '<section class="admin-panel"><h2>Billing details</h2><p class="admin-muted">No billing assignment found.</p></section>'; }
        $plan = $this->escape((string) ($row['plan_name'] ?? $row['plan_slug'] ?? 'Unknown plan'));
        $rawStatus = strtolower(trim((string) ($row['billing_status'] ?? $row['status'] ?? 'manual')));
        $status = $this->escape($rawStatus);
        $periodEndRaw = trim((string) ($row['current_period_ends_at'] ?? ''));
        $complimentaryUntilRaw = trim((string) ($row['complimentary_until'] ?? ''));
        $trialEndRaw = $rawStatus === 'trial' && $complimentaryUntilRaw !== ''
            ? $complimentaryUntilRaw
            : $periodEndRaw;
        $recurs = $trialEndRaw !== ''
            ? $this->escape($this->displayBillingTime($trialEndRaw))
            : 'Not set';
        $trialDetails = $rawStatus === 'trial'
            ? $this->trialPeriodDetails($trialEndRaw)
            : '';
        $subscription = $this->escape((string) ($row['stripe_subscription_id'] ?? '')) ?: 'Not connected';
        $latest = isset($row['latest_charge_cents']) ? '$' . number_format(((int) $row['latest_charge_cents']) / 100, 2) : '$0.00';
        $pending = trim((string) ($row['pending_change_type'] ?? '')) !== '' ? $this->escape((string) $row['pending_change_type']) . ' to ' . $this->escape((string) ($row['pending_plan_slug'] ?? 'selected plan')) . ' on ' . $this->escape((string) ($row['pending_effective_at'] ?? 'not set')) : 'None';
        return <<<HTML
<section class="admin-panel">
    <h2>Billing details</h2>
    <p><strong>Plan:</strong> {$plan}</p>
    <p><strong>Billing status:</strong> {$status}</p>
    {$trialDetails}
    <p><strong>Recurring billing date:</strong> {$recurs}</p>
    <p><strong>Stripe subscription:</strong> {$subscription}</p>
    <p><strong>Latest charge:</strong> {$latest}</p>
    <p><strong>Pending change:</strong> {$pending}</p>
</section>
HTML;
    }

    private function trialPeriodDetails(string $periodEndRaw): string
    {
        if ($periodEndRaw === '') {
            return '<p><strong>Trial period:</strong> Expiration date is not set.</p>';
        }

        try {
            $utc = new \DateTimeZone('UTC');
            $end = new \DateTimeImmutable($periodEndRaw, $utc);
            $now = new \DateTimeImmutable('now', $utc);
        } catch (\Throwable) {
            return '<p><strong>Trial period:</strong> Expiration date is invalid.</p>';
        }

        $displayEnd = $this->displayBillingTime($periodEndRaw);
        $relative = $this->relativeBillingTime($now, $end);

        if ($end <= $now) {
            return '<p><strong>Trial period:</strong> Expired ' . $this->escape($relative) . ' on ' . $this->escape($displayEnd) . '.</p>';
        }

        return '<p><strong>Trial period:</strong> Ends ' . $this->escape($displayEnd) . '; billing starts ' . $this->escape($relative) . '.</p>';
    }

    private function displayBillingTime(string $raw): string
    {
        try {
            $value = new \DateTimeImmutable($raw, new \DateTimeZone('UTC'));
            $timezone = new \DateTimeZone((string) ($GLOBALS['artsfolio_user_timezone'] ?? date_default_timezone_get()));
        } catch (\Throwable) {
            return $raw;
        }
        return $value->setTimezone($timezone)->format('M j, Y g:i A T');
    }

    private function relativeBillingTime(\DateTimeImmutable $from, \DateTimeImmutable $to): string
    {
        $future = $to >= $from;
        $interval = $future ? $from->diff($to) : $to->diff($from);
        $parts = [];
        if ($interval->y > 0) { $parts[] = $interval->y . ' ' . ($interval->y === 1 ? 'year' : 'years'); }
        if ($interval->m > 0) { $parts[] = $interval->m . ' ' . ($interval->m === 1 ? 'month' : 'months'); }
        if ($interval->d > 0) { $parts[] = $interval->d . ' ' . ($interval->d === 1 ? 'day' : 'days'); }
        if ($parts === []) {
            if ($interval->h > 0) { $parts[] = $interval->h . ' ' . ($interval->h === 1 ? 'hour' : 'hours'); }
            elseif ($interval->i > 0) { $parts[] = $interval->i . ' ' . ($interval->i === 1 ? 'minute' : 'minutes'); }
            else { $parts[] = 'less than a minute'; }
        }
        $duration = implode(', ', array_slice($parts, 0, 2));
        return $future ? 'in ' . $duration : $duration . ' ago';
    }

    private function complementaryChecked(int $tenantId): string
    {
        try {
            $stmt = $this->pdo()->prepare('SELECT complementary FROM tenants WHERE id = :tenant_id LIMIT 1');
            $stmt->execute(['tenant_id' => $tenantId]);
            return (int) $stmt->fetchColumn() === 1 ? ' checked' : '';
        } catch (\Throwable) {
            return '';
        }
    }


    /**
     * Search all tenants for the platform tenant list.
     *
     * @return array<int,array<string,mixed>>
     */

private function pdo(): \PDO
    {
        $ref = new \ReflectionProperty($this->tenants, 'pdo');
        $ref->setAccessible(true);
        return $ref->getValue($this->tenants);
    }

    public function updateTenantUserPassword(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManageTenants($currentUser) || !$this->users || !$this->hasher || !$this->csrf) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }

        $tenantId = (int) ($_POST['tenant_id'] ?? 0);
        $userId = (int) ($_POST['user_id'] ?? 0);
        $password = (string) ($_POST['new_password'] ?? '');
        if ($tenantId < 1 || $userId < 1 || strlen($password) < 12 || !$this->users->userBelongsToTenant($tenantId, $userId)) {
            return Response::html('<h1>Invalid password update request</h1>', 422);
        }

        $this->users->updatePasswordHash($userId, $this->hasher->hash($password));
        $this->auditLog?->record('platform.tenant_user.password_changed', $tenantId, (int) ($currentUser['user_id'] ?? 0), 'user', (string) $userId, ['tenant_id' => $tenantId], $request->server('REMOTE_ADDR'));
        FlashMessages::success('Tenant user password updated.');

        return new Response('', 303, ['Location' => '/platform/admin/tenants/' . $tenantId . '?notice=password-updated']);
    }


    public function updateTenantStatus(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManageTenants($currentUser) || !$this->csrf) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }

        $tenantId = (int) ($_POST['tenant_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        if ($tenantId < 1 || !in_array($status, ['trial', 'active', 'suspended', 'archived'], true)) {
            return Response::html('<h1>Invalid tenant status request</h1>', 422);
        }

        $this->tenants->setStatus($tenantId, $status);
        $this->auditLog?->record('platform.tenant.status_changed', $tenantId, (int) ($currentUser['user_id'] ?? 0), 'tenant', (string) $tenantId, ['status' => $status], $request->server('REMOTE_ADDR'));
        FlashMessages::success("Tenant {$tenantId} status changed to {$status}.");

        return new Response('', 303, ['Location' => '/platform/admin/tenants?notice=status-updated']);
    }

    private function tenantStatusActions(int $tenantId, string $status, string $csrf): string
    {
        $targets = ['active' => 'Activate', 'suspended' => 'Suspend'];
        $html = '';
        foreach ($targets as $target => $label) {
            if ($target === $status) {
                continue;
            }
            $html .= '<form method="post" action="/platform/admin/tenants/status" class="admin-inline-form">'
                . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
                . '<input type="hidden" name="tenant_id" value="' . $tenantId . '">'
                . '<input type="hidden" name="status" value="' . $this->escape($target) . '">'
                . '<button type="submit">' . $this->escape($label) . '</button>'
                . '</form>';
        }

        $html .= '<form method="post" action="/platform/admin/tenants/delete" class="admin-inline-form" onsubmit="this.confirm_delete.value = prompt(&quot;Type delete to remove this tenant from active platform lists. Data is soft-deleted, not physically destroyed.&quot;) || &quot;&quot;; return this.confirm_delete.value === &quot;delete&quot;;">'
            . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
            . '<input type="hidden" name="tenant_id" value="' . $tenantId . '">'
            . '<input type="hidden" name="confirm_delete" value="">'
            . '<button type="submit">Delete</button>'
            . '</form>';

        return $html;
    }

    public function suspend(Request $request, ?array $currentUser): Response
    {
        return $this->tenantLifecycle($request, $currentUser, 'suspend');
    }

    public function delete(Request $request, ?array $currentUser): Response
    {
        return $this->tenantLifecycle($request, $currentUser, 'delete');
    }

    private function tenantLifecycle(Request $request, ?array $currentUser, string $action): Response
    {
        if (!$this->canManageTenants($currentUser) || !$this->csrf) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }
        $tenantId = (int) ($_POST['tenant_id'] ?? 0);
        if ($tenantId < 1) {
            return Response::html('<h1>Invalid tenant lifecycle request</h1>', 422);
        }
        if ($action === 'delete' && strtolower((string) ($_POST['confirm_delete'] ?? '')) !== 'delete') {
            return Response::html('<h1>Tenant delete confirmation required</h1>', 422);
        }
        if ($action === 'delete') {
            $this->tenants->deleteTenant($tenantId);
        } else {
            $this->tenants->suspendTenant($tenantId);
        }
        $this->auditLog?->record('platform.tenant.' . $action, null, (int) ($currentUser['user_id'] ?? 0), 'tenant', (string) $tenantId, [], $request->server('REMOTE_ADDR'));
        FlashMessages::success('Tenant ' . $action . 'd.');
        return new Response('', 303, ['Location' => '/platform/admin/tenants?notice=' . $action]);
    }

private function canViewTenants(?array $currentUser): bool
    {
        return $this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT]);
    }

    private function canManageTenants(?array $currentUser): bool
    {
        return $this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN]);
    }

    private function escape(string $value): string
    {
        return AdminLayout::escape($value);
    }

    private function findTenant(int $tenantId): ?array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT
                t.id,
                t.uuid,
                t.slug,
                t.name,
                t.status,
                t.created_at,
                COUNT(td.id) AS domain_count
             FROM tenants t
             LEFT JOIN tenant_domains td ON td.tenant_id = t.id
             WHERE t.id = :tenant_id
               AND t.status <> 'deleted'
             GROUP BY t.id, t.uuid, t.slug, t.name, t.status, t.created_at
             LIMIT 1"
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $tenant = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $tenant ?: null;
    }


    /**
     * Search all non-deleted tenants for the platform tenant list.
     *
     * Results are shaped like TenantAdminRepository::latest() so the existing
     * table rendering and tenant drill-in links stay consistent.
     *
     * @return array<int,array<string,mixed>>
     */
    private function searchTenants(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return $this->tenants->latest();
        }

        $stmt = $this->pdo()->prepare(
            "SELECT
                t.id,
                t.uuid,
                t.slug,
                t.name,
                t.status,
                t.created_at,
                COUNT(td.id) AS domain_count
             FROM tenants t
             LEFT JOIN tenant_domains td ON td.tenant_id = t.id
             LEFT JOIN tenant_plan_assignments tpa ON tpa.id = (
                    SELECT MAX(tpa2.id)
                    FROM tenant_plan_assignments tpa2
                    WHERE tpa2.tenant_id = t.id
             )
             LEFT JOIN plans p ON p.id = tpa.plan_id
             WHERE t.status <> 'deleted'
               AND CONCAT_WS(' ',
                    t.id,
                    t.uuid,
                    t.slug,
                    t.name,
                    t.status,
                    t.created_at,
                    COALESCE(td.hostname, ''),
                    COALESCE(tpa.billing_status, ''),
                    COALESCE(tpa.stripe_subscription_id, ''),
                    COALESCE(p.name, ''),
                    COALESCE(p.slug, '')
               ) LIKE :tenant_search
             GROUP BY t.id, t.uuid, t.slug, t.name, t.status, t.created_at
             ORDER BY t.id DESC
             LIMIT 250"
        );
        $stmt->execute(['tenant_search' => '%' . $query . '%']);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }


}

// End of file.
