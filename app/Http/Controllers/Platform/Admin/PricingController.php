<?php

/**
 * Platform-admin pricing editor and billing disclosure controls.
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
use App\Platform\Settings\PlatformSettingsRepository;
use App\Support\Flash\FlashMessages;
use App\Support\Security\CsrfTokenService;
use PDO;

final class PricingController
{
    public function __construct(
        private readonly RequirePlatformRole $roles,
        private readonly PDO $pdo,
        private readonly ?PlatformSettingsRepository $settings = null,
        private readonly ?CsrfTokenService $csrf = null,
        private readonly ?AuditLogRepository $auditLog = null,
    ) {
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $csrf = $this->csrf ? AdminLayout::escape($this->csrf->getOrCreate()) : '';
        $canEdit = $this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN]) && $this->csrf !== null && $this->settings !== null;
        $commissionPercent = number_format($this->commissionBasisPoints() / 100, 2, '.', '');
        $rows = '';
        foreach ($this->plans() as $plan) {
            $id = (int) $plan['id'];
            $slug = AdminLayout::escape((string) $plan['slug']);
            $name = AdminLayout::escape((string) $plan['name']);
            $description = AdminLayout::escape((string) ($plan['description'] ?? ''));
            $monthly = number_format(((int) $plan['monthly_price_cents']) / 100, 2, '.', '');
            $artworks = (string) (int) ($plan['allowed_artworks'] ?? 0);
            $emails = (string) (int) ($plan['allowed_email_addresses'] ?? 0);
            $storage = (string) (int) ($plan['allowed_storage_gb'] ?? 0);
            $contacts = (string) (int) ($plan['allowed_contact_messages'] ?? 0);
            $admins = (string) (int) ($plan['allowed_admin_users'] ?? 0);
            $ccPercent = number_format(((int) ($plan['credit_card_fee_basis_points'] ?? 290)) / 100, 2, '.', '');
            $ccFixed = number_format(((int) ($plan['credit_card_fixed_fee_cents'] ?? 30)) / 100, 2, '.', '');
            $allowSales = ((int) ($plan['allow_sales'] ?? 0)) === 1 ? ' checked' : '';
            $order = (string) (int) ($plan['display_order'] ?? 100);
            $domain = ((int) $plan['custom_domain_included, admin_user_limit']) === 1 ? ' checked' : '';
            $active = ((int) $plan['is_active']) === 1 ? ' checked' : '';
            if ($canEdit) {
                $rows .= <<<HTML
<tr>
    <td><code>{$slug}</code><input type="hidden" name="plans[{$id}][id]" value="{$id}"></td>
    <td><input type="text" name="plans[{$id}][name]" value="{$name}" required></td>
    <td><input type="number" name="plans[{$id}][monthly_price_dollars]" min="0" step="0.01" value="{$monthly}"></td>
    <td><input type="number" name="plans[{$id}][allowed_artworks]" min="0" value="{$artworks}"></td>
    <td><input type="number" name="plans[{$id}][allowed_email_addresses]" min="0" value="{$emails}"></td>
    <td><input type="number" name="plans[{$id}][allowed_storage_gb]" min="0" value="{$storage}"></td>
    <td><input type="number" name="plans[{$id}][allowed_contact_messages]" min="0" value="{$contacts}"></td>
    <td><input type="number" name="plans[{$id}][allowed_admin_users]" min="0" value="{$admins}"></td>
    <td><label><input type="checkbox" name="plans[{$id}][custom_domain_included]" value="1"{$domain}> included</label></td>
    <td><label><input type="checkbox" name="plans[{$id}][allow_sales]" value="1"{$allowSales}> enabled</label></td>
    <td><input type="number" name="plans[{$id}][credit_card_fee_percent]" min="0" max="100" step="0.01" value="{$ccPercent}"></td>
    <td><input type="number" name="plans[{$id}][credit_card_fixed_fee_dollars]" min="0" step="0.01" value="{$ccFixed}"></td>
    <td><label><input type="checkbox" name="plans[{$id}][is_active]" value="1"{$active}> active</label></td>
    <td><input type="number" name="plans[{$id}][display_order]" min="0" value="{$order}"></td>
</tr>
<tr><td></td><td colspan="13"><label>Description<textarea name="plans[{$id}][description]" rows="2">{$description}</textarea></label></td></tr>
HTML;
            } else {
                $price = '$' . number_format(((int) $plan['monthly_price_cents']) / 100, 2);
                $rows .= '<tr><td><code>' . $slug . '</code></td><td>' . $name . '</td><td>' . $price . '</td><td>' . $artworks . '</td><td>' . $emails . '</td><td>' . $ccPercent . '% + $' . $ccFixed . '</td><td>' . (((int) $plan['is_active']) ? 'active' : 'inactive') . '</td><td>' . $order . '</td></tr>';
            }
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="14">No plans found.</td></tr>';
        }

        $button = $canEdit ? '<button type="submit">Save pricing</button>' : '';
        $formOpen = $canEdit ? '<form class="plan-edit-form" class="admin-form" method="post" action="/platform/admin/pricing"><input type="hidden" name="csrf_token" value="' . $csrf . '">' : '';
        $formClose = $canEdit ? '</form>' : '';

        return Response::html(AdminLayout::render(title: 'Platform Pricing', body: <<<HTML
<p class="admin-muted">Set public pricing, plan limits, platform sales commission, and plan-specific card processing fee disclosure. Complimentary tenants waive only subscription billing; they still pay platform commission and card processing fees on sales.</p>
{$formOpen}
<section class="admin-panel"><h2>Platform sales commission</h2><label>Commission on sales, percent<input type="number" name="platform_sales_commission_percent" min="0" max="100" step="0.01" value="{$commissionPercent}"></label><p class="admin-muted">Current disclosure: ArtsFolio commission is {$commissionPercent}% of platform-processed sales.</p></section>
<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Slug</th><th>Name</th><th>Monthly</th><th>Allowed artworks</th><th>Allowed email addresses</th><th>Storage GB</th><th>Contact messages</th><th>Admin users</th><th>Custom domain</th><th>Sales</th><th>CC %</th><th>CC fixed</th><th>Status</th><th>Order</th></tr></thead><tbody>{$rows}</tbody></table></div>
<section class="admin-panel"><h2>Create plan</h2>
<div class="admin-form-grid three">
<label>Slug<input name="new_plan[slug]" pattern="[a-z0-9-]+" placeholder="artist-plus"></label>
<label>Name<input name="new_plan[name]" placeholder="Artist Plus"></label>
<label>Monthly price<input type="number" name="new_plan[monthly_price_dollars]" min="0" step="0.01" value="0.00"></label>
<label>Allowed artworks<input type="number" name="new_plan[allowed_artworks]" min="0" value="100"></label>
<label>Allowed email addresses<input type="number" name="new_plan[allowed_email_addresses]" min="0" value="500"></label>
<label>Storage GB<input type="number" name="new_plan[allowed_storage_gb]" min="0" value="5"></label>
<label>Contact messages<input type="number" name="new_plan[allowed_contact_messages]" min="0" value="100"></label>
<label>Admin users<input type="number" name="new_plan[allowed_admin_users]" min="0" value="3"></label>
<label>Credit card percent<input type="number" name="new_plan[credit_card_fee_percent]" min="0" max="100" step="0.01" value="2.90"></label>
<label>Credit card fixed fee<input type="number" name="new_plan[credit_card_fixed_fee_dollars]" min="0" step="0.01" value="0.30"></label>
<label>Display order<input type="number" name="new_plan[display_order]" min="0" value="50"></label>
<label><input type="checkbox" name="new_plan[custom_domain_included]" value="1"> Custom domain included</label>
<label><input type="checkbox" name="new_plan[allow_sales]" value="1"> Allow sales</label>
<label><input type="checkbox" name="new_plan[is_active]" value="1" checked> Active</label>
</div>
<label>Description<textarea name="new_plan[description]" rows="2" placeholder="Who this plan is for and what it includes."></textarea></label>
<p class="admin-muted">Leave slug and name blank when you only want to edit existing plans.</p>
</section>
{$button}
{$formClose}
<p><a class="admin-button" href="/pricing">View public pricing page</a> <a class="admin-button" href="/platform/admin/platform-settings">Platform settings</a></p>
HTML, active: 'pricing'));
    }

    public function update(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN]) || !$this->csrf || !$this->settings) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }

        $commissionPercent = max(0.0, min(100.0, (float) ($_POST['platform_sales_commission_percent'] ?? 0)));
        $commissionBasisPoints = (int) round($commissionPercent * 100);
        $before = ['commission_basis_points' => $this->commissionBasisPoints(), 'plans' => $this->plans()];
        $this->settings->set('platform_sales_commission_basis_points', (string) $commissionBasisPoints);

        foreach ((array) ($_POST['plans'] ?? []) as $plan) {
            $id = (int) ($plan['id'] ?? 0);
            $name = trim((string) ($plan['name'] ?? ''));
            if ($id < 1 || $name === '') {
                continue;
            }
            $stmt = $this->pdo->prepare('UPDATE plans SET name = :name, monthly_price_cents = :monthly_price_cents, description = :description, custom_domain_included = :custom_domain_included, allowed_artworks = :allowed_artworks, allowed_email_addresses = :allowed_email_addresses, allowed_storage_gb = :allowed_storage_gb, allowed_contact_messages = :allowed_contact_messages, allowed_admin_users = :allowed_admin_users, allow_sales = :allow_sales, credit_card_fee_basis_points = :credit_card_fee_basis_points, credit_card_fixed_fee_cents = :credit_card_fixed_fee_cents, display_order = :display_order, is_active = :is_active WHERE id = :id');
            $stmt->execute($this->planParams($plan, $id, $name));
        }

        $newPlan = is_array($_POST['new_plan'] ?? null) ? $_POST['new_plan'] : [];
        $newSlug = strtolower(trim((string) ($newPlan['slug'] ?? '')));
        $newName = trim((string) ($newPlan['name'] ?? ''));
        if ($newSlug !== '' || $newName !== '') {
            if (!preg_match('/^[a-z0-9-]+$/', $newSlug) || $newName === '') {
                return Response::html('<h1>New plan requires a lowercase slug and name</h1>', 422);
            }
            $insert = $this->pdo->prepare('INSERT INTO plans (slug, name, monthly_price_cents, description, custom_domain_included, allowed_artworks, allowed_email_addresses, allowed_storage_gb, allowed_contact_messages, allowed_admin_users, allow_sales, credit_card_fee_basis_points, credit_card_fixed_fee_cents, display_order, is_active) VALUES (:slug, :name, :monthly_price_cents, :description, :custom_domain_included, :allowed_artworks, :allowed_email_addresses, :allowed_storage_gb, :allowed_contact_messages, :allowed_admin_users, :allow_sales, :credit_card_fee_basis_points, :credit_card_fixed_fee_cents, :display_order, :is_active) ON DUPLICATE KEY UPDATE name = VALUES(name), monthly_price_cents = VALUES(monthly_price_cents), description = VALUES(description), custom_domain_included = VALUES(custom_domain_included), allowed_artworks = VALUES(allowed_artworks), allowed_email_addresses = VALUES(allowed_email_addresses), allowed_storage_gb = VALUES(allowed_storage_gb), allowed_contact_messages = VALUES(allowed_contact_messages), allowed_admin_users = VALUES(allowed_admin_users), allow_sales = VALUES(allow_sales), credit_card_fee_basis_points = VALUES(credit_card_fee_basis_points), credit_card_fixed_fee_cents = VALUES(credit_card_fixed_fee_cents), display_order = VALUES(display_order), is_active = VALUES(is_active)');
            $params = $this->planParams($newPlan, null, $newName);
            $params['slug'] = $newSlug;
            unset($params['id']);
            $insert->execute($params);
        }

        $this->auditLog?->record('platform.pricing.updated', null, isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null, 'plans', 'pricing', ['before' => $before, 'after' => ['commission_basis_points' => $commissionBasisPoints, 'plans' => $this->plans()]], $request->server('REMOTE_ADDR'));
        FlashMessages::success('Platform pricing saved.');
        return new Response('', 303, ['Location' => '/platform/admin/pricing?notice=saved']);
    }

    private function planParams(array $plan, ?int $id, string $name): array
    {
        $params = [
            'name' => $name,
            'monthly_price_cents' => max(0, (int) round(((float) ($plan['monthly_price_dollars'] ?? 0)) * 100)),
            'description' => trim((string) ($plan['description'] ?? '')),
            'custom_domain_included' => isset($plan['custom_domain_included']) ? 1 : 0,
            'allowed_artworks' => max(0, (int) ($plan['allowed_artworks'] ?? 0)),
            'allowed_email_addresses' => max(0, (int) ($plan['allowed_email_addresses'] ?? 0)),
            'allowed_storage_gb' => max(0, (int) ($plan['allowed_storage_gb'] ?? 0)),
            'allowed_contact_messages' => max(0, (int) ($plan['allowed_contact_messages'] ?? 0)),
            'allowed_admin_users' => max(0, (int) ($plan['allowed_admin_users'] ?? 0)),
            'allow_sales' => isset($plan['allow_sales']) ? 1 : 0,
            'credit_card_fee_basis_points' => max(0, min(10000, (int) round(((float) ($plan['credit_card_fee_percent'] ?? 2.9)) * 100))),
            'credit_card_fixed_fee_cents' => max(0, (int) round(((float) ($plan['credit_card_fixed_fee_dollars'] ?? 0.30)) * 100)),
            'display_order' => max(0, (int) ($plan['display_order'] ?? 100)),
            'is_active' => isset($plan['is_active']) ? 1 : 0,
        ];
        if ($id !== null) {
            $params['id'] = $id;
        }
        return $params;
    }

    private function plans(): array
    {
        if (!$this->tableExists('plans')) {
            return [];
        }
        $columns = $this->planColumns();
        $select = 'id, slug, name, monthly_price_cents, custom_domain_included, is_active, created_at'
            . ($columns['description'] ? ', description' : ', NULL AS description')
            . ($columns['allowed_artworks'] ? ', allowed_artworks' : ', NULL AS allowed_artworks')
            . ($columns['allowed_email_addresses'] ? ', allowed_email_addresses' : ', NULL AS allowed_email_addresses')
            . ($columns['allowed_storage_gb'] ? ', allowed_storage_gb' : ', 0 AS allowed_storage_gb')
            . ($columns['allowed_contact_messages'] ? ', allowed_contact_messages' : ', 0 AS allowed_contact_messages')
            . ($columns['allowed_admin_users'] ? ', allowed_admin_users' : ', 0 AS allowed_admin_users')
            . ($columns['allow_sales'] ? ', allow_sales' : ', 0 AS allow_sales')
            . ($columns['credit_card_fee_basis_points'] ? ', credit_card_fee_basis_points' : ', 290 AS credit_card_fee_basis_points')
            . ($columns['credit_card_fixed_fee_cents'] ? ', credit_card_fixed_fee_cents' : ', 30 AS credit_card_fixed_fee_cents')
            . ($columns['display_order'] ? ', display_order' : ', 100 AS display_order');
        return $this->pdo->query("SELECT {$select} FROM plans ORDER BY display_order ASC, monthly_price_cents ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    private function commissionBasisPoints(): int
    {
        return max(0, min(10000, (int) ($this->settings?->get('platform_sales_commission_basis_points', '500') ?? '500')));
    }

    private function planColumns(): array
    {
        $columns = ['description' => false, 'allowed_artworks' => false, 'allowed_email_addresses' => false, 'allowed_storage_gb' => false, 'allowed_contact_messages' => false, 'allowed_admin_users' => false, 'allow_sales' => false, 'credit_card_fee_basis_points' => false, 'credit_card_fixed_fee_cents' => false, 'display_order' => false];
        $stmt = $this->pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => 'plans']);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $column) {
            if (array_key_exists((string) $column, $columns)) {
                $columns[(string) $column] = true;
            }
        }
        return $columns;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    }
    /**
     * Format plan admin-user limits for pricing display.
     */
    private function formatAdminUsers(mixed $value): string
    {
        if ($value === null || $value === '' || (int) $value < 0) {
            return 'Unlimited admin users';
        }

        $count = (int) $value;
        return $count === 1 ? '1 admin user' : $count . ' admin users';
    }
}
