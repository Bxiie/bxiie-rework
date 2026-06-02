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
        $commission = $this->commissionBasisPoints();
        $rows = '';
        foreach ($this->plans() as $plan) {
            $id = (int) $plan['id'];
            $slug = AdminLayout::escape((string) $plan['slug']);
            $name = AdminLayout::escape((string) $plan['name']);
            $description = AdminLayout::escape((string) ($plan['description'] ?? ''));
            $monthly = number_format(((int) $plan['monthly_price_cents']) / 100, 2, '.', '');
            $artworks = (string) (int) ($plan['allowed_artworks'] ?? 0);
            $emails = (string) (int) ($plan['allowed_email_addresses'] ?? 0);
            $order = (string) (int) ($plan['display_order'] ?? 100);
            $domain = ((int) $plan['custom_domain_included']) === 1 ? ' checked' : '';
            $active = ((int) $plan['is_active']) === 1 ? ' checked' : '';
            if ($canEdit) {
                $rows .= <<<HTML
<tr>
    <td><code>{$slug}</code><input type="hidden" name="plans[{$id}][id]" value="{$id}"></td>
    <td><input type="text" name="plans[{$id}][name]" value="{$name}" required></td>
    <td><input type="number" name="plans[{$id}][monthly_price_dollars]" min="0" step="0.01" value="{$monthly}"></td>
    <td><input type="number" name="plans[{$id}][allowed_artworks]" min="0" value="{$artworks}"></td>
    <td><input type="number" name="plans[{$id}][allowed_email_addresses]" min="0" value="{$emails}"></td>
    <td><label><input type="checkbox" name="plans[{$id}][custom_domain_included]" value="1"{$domain}> included</label></td>
    <td><label><input type="checkbox" name="plans[{$id}][is_active]" value="1"{$active}> active</label></td>
    <td><input type="number" name="plans[{$id}][display_order]" min="0" value="{$order}"></td>
</tr>
<tr><td></td><td colspan="7"><label>Description<textarea name="plans[{$id}][description]" rows="2">{$description}</textarea></label></td></tr>
HTML;
            } else {
                $price = '$' . number_format(((int) $plan['monthly_price_cents']) / 100, 2);
                $rows .= '<tr><td><code>' . $slug . '</code></td><td>' . $name . '</td><td>' . $price . '</td><td>' . $artworks . '</td><td>' . $emails . '</td><td>' . (((int) $plan['custom_domain_included']) ? 'yes' : 'no') . '</td><td>' . (((int) $plan['is_active']) ? 'active' : 'inactive') . '</td><td>' . $order . '</td></tr>';
            }
        }
        if ($rows === '') { $rows = '<tr><td colspan="8">No plans found.</td></tr>'; }

        $button = $canEdit ? '<button type="submit">Save pricing</button>' : '';
        $formOpen = $canEdit ? '<form class="admin-form" method="post" action="/platform/admin/pricing"><input type="hidden" name="csrf_token" value="' . $csrf . '">' : '';
        $formClose = $canEdit ? '</form>' : '';
        $commissionPercent = number_format($commission / 100, 2, '.', '');

        return Response::html(AdminLayout::render(title: 'Platform Pricing', body: <<<HTML
<p class="admin-muted">Set public pricing, plan limits, and platform sales commission disclosure. Commission is shown to prospective users on the pricing page and to current users through billing context.</p>
{$formOpen}
<section class="admin-panel"><h2>Platform sales commission</h2><label>Commission on sales, percent<input type="number" name="platform_sales_commission_percent" min="0" max="100" step="0.01" value="{$commissionPercent}"></label><p class="admin-muted">Current disclosure: ArtsFolio commission is {$commissionPercent}% of platform-processed sales.</p></section>
<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Slug</th><th>Name</th><th>Monthly</th><th>Allowed artworks</th><th>Allowed email addresses</th><th>Custom domain</th><th>Status</th><th>Order</th></tr></thead><tbody>{$rows}</tbody></table></div>
<section class="admin-panel"><h2>Create plan</h2>
<div class="admin-form-grid three">
<label>Slug<input name="new_plan[slug]" pattern="[a-z0-9-]+" placeholder="artist-plus"></label>
<label>Name<input name="new_plan[name]" placeholder="Artist Plus"></label>
<label>Monthly price<input type="number" name="new_plan[monthly_price_dollars]" min="0" step="0.01" value="0.00"></label>
<label>Allowed artworks<input type="number" name="new_plan[allowed_artworks]" min="0" value="100"></label>
<label>Allowed email addresses<input type="number" name="new_plan[allowed_email_addresses]" min="0" value="500"></label>
<label>Display order<input type="number" name="new_plan[display_order]" min="0" value="50"></label>
<label><input type="checkbox" name="new_plan[custom_domain_included]" value="1"> Custom domain included</label>
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
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }

        $commissionPercent = max(0.0, min(100.0, (float) ($_POST['platform_sales_commission_percent'] ?? 0)));
        $commissionBasisPoints = (int) round($commissionPercent * 100);
        $before = ['commission_basis_points' => $this->commissionBasisPoints(), 'plans' => $this->plans()];
        $this->settings->set('platform_sales_commission_basis_points', (string) $commissionBasisPoints);

        $plans = is_array($_POST['plans'] ?? null) ? $_POST['plans'] : [];
        foreach ($plans as $plan) {
            $id = (int) ($plan['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $name = trim((string) ($plan['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $priceCents = max(0, (int) round(((float) ($plan['monthly_price_dollars'] ?? 0)) * 100));
            $description = trim((string) ($plan['description'] ?? ''));
            $allowedArtworks = max(0, (int) ($plan['allowed_artworks'] ?? 0));
            $allowedEmails = max(0, (int) ($plan['allowed_email_addresses'] ?? 0));
            $displayOrder = max(0, (int) ($plan['display_order'] ?? 100));
            $customDomain = isset($plan['custom_domain_included']) ? 1 : 0;
            $active = isset($plan['is_active']) ? 1 : 0;
            $stmt = $this->pdo->prepare('UPDATE plans SET name = :name, monthly_price_cents = :monthly_price_cents, description = :description, custom_domain_included = :custom_domain_included, allowed_artworks = :allowed_artworks, allowed_email_addresses = :allowed_email_addresses, display_order = :display_order, is_active = :is_active WHERE id = :id');
            $stmt->execute(['id' => $id, 'name' => $name, 'monthly_price_cents' => $priceCents, 'description' => $description, 'custom_domain_included' => $customDomain, 'allowed_artworks' => $allowedArtworks, 'allowed_email_addresses' => $allowedEmails, 'display_order' => $displayOrder, 'is_active' => $active]);
        }

        $newPlan = is_array($_POST['new_plan'] ?? null) ? $_POST['new_plan'] : [];
        $newSlug = strtolower(trim((string) ($newPlan['slug'] ?? '')));
        $newName = trim((string) ($newPlan['name'] ?? ''));
        if ($newSlug !== '' || $newName !== '') {
            if (!preg_match('/^[a-z0-9-]+$/', $newSlug) || $newName === '') {
                return Response::html('<h1>New plan requires a lowercase slug and name</h1>', 422);
            }
            $newPriceCents = max(0, (int) round(((float) ($newPlan['monthly_price_dollars'] ?? 0)) * 100));
            $newDescription = trim((string) ($newPlan['description'] ?? ''));
            $newAllowedArtworks = max(0, (int) ($newPlan['allowed_artworks'] ?? 0));
            $newAllowedEmails = max(0, (int) ($newPlan['allowed_email_addresses'] ?? 0));
            $newDisplayOrder = max(0, (int) ($newPlan['display_order'] ?? 100));
            $newCustomDomain = isset($newPlan['custom_domain_included']) ? 1 : 0;
            $newActive = isset($newPlan['is_active']) ? 1 : 0;
            $insert = $this->pdo->prepare('INSERT INTO plans (slug, name, monthly_price_cents, description, custom_domain_included, allowed_artworks, allowed_email_addresses, display_order, is_active) VALUES (:slug, :name, :monthly_price_cents, :description, :custom_domain_included, :allowed_artworks, :allowed_email_addresses, :display_order, :is_active) ON DUPLICATE KEY UPDATE name = VALUES(name), monthly_price_cents = VALUES(monthly_price_cents), description = VALUES(description), custom_domain_included = VALUES(custom_domain_included), allowed_artworks = VALUES(allowed_artworks), allowed_email_addresses = VALUES(allowed_email_addresses), display_order = VALUES(display_order), is_active = VALUES(is_active)');
            $insert->execute(['slug' => $newSlug, 'name' => $newName, 'monthly_price_cents' => $newPriceCents, 'description' => $newDescription, 'custom_domain_included' => $newCustomDomain, 'allowed_artworks' => $newAllowedArtworks, 'allowed_email_addresses' => $newAllowedEmails, 'display_order' => $newDisplayOrder, 'is_active' => $newActive]);
        }

        $this->auditLog?->record('platform.pricing.updated', null, isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null, 'plans', 'pricing', ['before' => $before, 'after' => ['commission_basis_points' => $commissionBasisPoints, 'plans' => $this->plans()]], $request->server('REMOTE_ADDR'));
        FlashMessages::success('Platform pricing saved.');
        return new Response('', 303, ['Location' => '/platform/admin/pricing?notice=saved']);
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
            . ($columns['display_order'] ? ', display_order' : ', 100 AS display_order');
        return $this->pdo->query("SELECT {$select} FROM plans ORDER BY display_order ASC, monthly_price_cents ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    private function commissionBasisPoints(): int
    {
        return max(0, min(10000, (int) ($this->settings?->get('platform_sales_commission_basis_points', '500') ?? '500')));
    }

    private function planColumns(): array
    {
        $columns = ['description' => false, 'allowed_artworks' => false, 'allowed_email_addresses' => false, 'display_order' => false];
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
}

// End of file.
