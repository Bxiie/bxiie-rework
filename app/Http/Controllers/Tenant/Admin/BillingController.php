<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Tenancy\TenantContext;
use App\Support\Flash\FlashMessages;
use PDO;
use Throwable;

/**
 * Shows tenant admins the selected pricing tier and current feature usage.
 */
final class BillingController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly PDO $pdo,
    ) {
    }

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $plan = $this->currentPlan($tenant) ?? $this->fallbackPlan();
        $plans = $this->plans();
        $usage = $this->usage($tenant);
        $featureRows = $this->featureRows($plan, $usage, $tenant);
        $planOptions = '';
        foreach ($plans as $candidate) {
            $slug = $this->e((string) $candidate['slug']);
            $name = $this->e((string) $candidate['name']);
            $price = $this->money((int) ($candidate['monthly_price_cents'] ?? 0));
            $selected = (string) $candidate['slug'] === (string) $plan['slug'] ? ' selected' : '';
            $planOptions .= "<option value=\"{$slug}\"{$selected}>{$name} — {$price}</option>";
        }

        $economics = $this->salesEconomics($plan);
        $commission = $economics['commission_label'];
        $cardFees = $economics['card_label'];
        $payout100 = $this->payoutExample(10000, $economics);
        $payout1000 = $this->payoutExample(100000, $economics);
        $complementary = $this->isComplementary($tenant) ? '<p class="admin-notice admin-notice-info"><strong>Complementary plan:</strong> platform service billing is waived. Platform commission and credit card charges still apply to sales.</p>' : '';
        $csrf = $this->e($this->csrfToken());
        $planName = $this->e((string) $plan['name']);
        $price = $this->money((int) ($plan['monthly_price_cents'] ?? 0));
        $summary = $this->e((string) ($plan['description'] ?? 'ArtsFolio artist portfolio plan.'));

        $body = <<<HTML
<section class="admin-billing-summary">
  <div class="admin-panel">
    <p class="admin-muted">Current pricing tier</p>
    <h2>{$planName}</h2>
    <p class="billing-price">{$price}</p>
    <p>{$summary}</p>
    <p><strong>Platform sales commission:</strong> {$this->e($commission)}</p>
    <p><strong>Credit card charges:</strong> {$this->e($cardFees)}</p>
    <div class="admin-panel-subtle"><h3>Estimated seller proceeds</h3><p>On a $100 sale, estimated payout is <strong>{$this->e($payout100)}</strong>.</p><p>On a $1,000 sale, estimated payout is <strong>{$this->e($payout1000)}</strong>.</p><p class="admin-muted">Seller receives sale amount minus platform commission, minus credit card percentage, minus fixed credit card charge. Shipping, tax, refunds, and chargebacks are not included in this estimate.</p></div>
    {$complementary}
  </div>
  <div class="admin-panel">
    <p class="admin-muted">Change plan</p>
    <form method="post" action="/admin/billing/plan" class="admin-form">
      <input type="hidden" name="csrf_token" value="{$csrf}">
      <label>Plan<select name="plan_slug">{$planOptions}</select></label>
      <button type="submit">Update plan</button>
    </form>
    <p class="admin-muted">Upgrade and downgrade changes are recorded immediately. External billing collection remains a platform operations task until subscription billing is connected.</p>
  </div>
</section>

<section class="admin-panel admin-panel-wide">
  <h2>Feature usage by selected pricing tier</h2>
  <p class="admin-muted">These features match the platform-admin pricing setup fields.</p>
  <div class="admin-table-wrap"><table class="admin-table">
    <thead><tr><th>Feature</th><th>Included</th><th>Used</th><th>Status</th></tr></thead>
    <tbody>{$featureRows}</tbody>
  </table></div>
</section>
HTML;

        return Response::html(AdminLayout::render('Billing', $body));
    }

    public function updatePlan(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'owner'])) {
            return Response::html('<h1>Forbidden</h1><p>Only tenant owners may change plans.</p>', 403);
        }
        if (!$this->validCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }
        $slug = strtolower(trim((string) ($_POST['plan_slug'] ?? '')));
        $plan = $this->planBySlug($slug);
        if (!$plan) {
            return Response::html('<h1>Invalid plan</h1>', 422);
        }
        $this->assignPlan($tenant, (int) $plan['id']);
        $this->setSetting($tenant, 'billing_plan', (string) $plan['slug']);
        FlashMessages::success('Billing plan updated.');
        return new Response('', 303, ['Location' => '/admin/billing?notice=plan-updated']);
    }

    /**
     * Count billable custom-domain groups for plan usage.
     */
    private function countCustomDomainGroups(TenantContext $tenant): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT hostname
             FROM tenant_domains
             WHERE tenant_id = :tenant_id
               AND domain_type <> 'subdomain'
               AND hostname NOT LIKE '%.artsfol.io'"
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId]);

        $groups = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $hostname) {
            $groups[preg_replace('/^www\./', '', strtolower((string) $hostname))] = true;
        }

        return count($groups);
    }

    private function featureRows(array $plan, array $usage, TenantContext $tenant): string
    {
        $features = [
            'artworks' => ['Artwork records', (int) ($plan['allowed_artworks'] ?? 0), $usage['artworks']],
            'storage_gb' => ['Media storage GB', (int) ($plan['allowed_storage_gb'] ?? 0), $usage['storage_gb']],
            'email_signups' => ['Email subscribers', (int) ($plan['allowed_email_addresses'] ?? 0), $usage['email_signups']],
            'contact_messages' => ['Contact messages', (int) ($plan['allowed_contact_messages'] ?? 0), $usage['contact_messages']],
            'custom_domains' => ['Custom domains', (int) ($plan['custom_domain_included'] ?? 0), $usage['custom_domains']],
            'admin_users' => ['Admin users', (int) ($plan['allowed_admin_users'] ?? 0), $usage['admin_users']],
            'sales' => ['Online checkout', ((int) ($plan['allow_sales'] ?? 0) === 1 ? 'Included' : 'Paid-plan setting off'), ((int) ($plan['allow_sales'] ?? 0) === 1 ? 'Available' : 'Unavailable')],
            'platform_commission' => ['Platform sales commission', $this->salesEconomics($plan)['commission_label'], 'Shown at checkout/billing'],
            'credit_card_fees' => ['Credit card charges', $this->salesEconomics($plan)['card_label'], 'Deducted from sales'],
            'directory' => ['Directory/discovery listing', 'Opt-in', $this->truthy($this->setting($tenant, 'platform_directory_opt_in', '0')) ? 'Enabled' : 'Off'],
            'analytics' => ['Analytics events', ((int) ($plan['monthly_price_cents'] ?? 0) === 0 ? 'Basic' : 'Advanced'), $usage['analytics_events']],
        ];

        $rows = '';
        foreach ($features as $key => [$label, $included, $used]) {
            $status = is_numeric($included) ? $this->status((float) $used, (float) $included) : 'OK';
            if ($key === 'sales' && $included !== 'Included') {
                $status = 'Upgrade required';
            }
            $rows .= '<tr><td>' . $this->e((string) $label) . '</td><td>' . $this->e((string) $included) . '</td><td>' . $this->e((string) $used) . '</td><td>' . $this->e($status) . '</td></tr>';
        }

        return $rows;
    }

    private function plans(): array
    {
        if (!$this->tableExists('plans')) {
            return [$this->fallbackPlan()];
        }
        $stmt = $this->pdo->query('SELECT * FROM plans WHERE is_active = 1 ORDER BY display_order ASC, monthly_price_cents ASC, id ASC');
        $plans = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        return $plans !== [] ? $plans : [$this->fallbackPlan()];
    }

    private function currentPlan(TenantContext $tenant): ?array
    {
        if ($this->tableExists('tenant_plan_assignments')) {
            $stmt = $this->pdo->prepare('SELECT p.* FROM tenant_plan_assignments tpa JOIN plans p ON p.id = tpa.plan_id WHERE tpa.tenant_id = :tenant_id AND tpa.status IN ("trial", "active", "manual") ORDER BY tpa.id DESC LIMIT 1');
            $stmt->execute(['tenant_id' => $tenant->tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }
        return $this->planBySlug($this->setting($tenant, 'billing_plan', 'studio'));
    }

    private function planBySlug(string $slug): ?array
    {
        if (!$this->tableExists('plans')) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM plans WHERE slug = :slug AND is_active = 1 LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function assignPlan(TenantContext $tenant, int $planId): void
    {
        if (!$this->tableExists('tenant_plan_assignments')) {
            return;
        }
        $stmt = $this->pdo->prepare('INSERT INTO tenant_plan_assignments (tenant_id, plan_id, status) VALUES (:tenant_id, :plan_id, "manual") ON DUPLICATE KEY UPDATE plan_id = VALUES(plan_id), status = "manual"');
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'plan_id' => $planId]);
    }

    private function fallbackPlan(): array
    {
        return ['id' => 0, 'slug' => 'studio', 'name' => 'Studio', 'monthly_price_cents' => 1200, 'description' => 'For active artists.', 'allowed_artworks' => 250, 'allowed_storage_gb' => 5, 'allowed_email_addresses' => 2500, 'allowed_contact_messages' => 250, 'custom_domain_included' => 0, 'allowed_admin_users' => 3, 'allow_sales' => 1, 'credit_card_fee_basis_points' => 290, 'credit_card_fixed_fee_cents' => 30];
    }

    private function usage(TenantContext $tenant): array
    {
        return [
            'artworks' => $this->countRows('artworks', $tenant),
            'storage_gb' => $this->storageGb($tenant),
            'email_signups' => $this->countRows('email_signups', $tenant),
            'contact_messages' => $this->countRows('contact_messages', $tenant),
            'custom_domains' => $this->countCustomDomainGroups($tenant),
            'admin_users' => $this->countMemberships($tenant),
            'analytics_events' => $this->countRows('analytics_events', $tenant),
        ];
    }

    private function salesEconomics(array $plan): array
    {
        $commissionBasisPoints = 500;
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = 'platform_sales_commission_basis_points' LIMIT 1");
            $stmt->execute();
            $commissionBasisPoints = max(0, min(10000, (int) ($stmt->fetchColumn() ?: 500)));
        } catch (Throwable) {
            // Keep the billing screen available even if platform settings are not ready.
        }

        $cardBasisPoints = max(0, min(10000, (int) ($plan['credit_card_fee_basis_points'] ?? 290)));
        $cardFixedCents = max(0, (int) ($plan['credit_card_fixed_fee_cents'] ?? 30));
        return [
            'commission_basis_points' => $commissionBasisPoints,
            'card_basis_points' => $cardBasisPoints,
            'card_fixed_cents' => $cardFixedCents,
            'commission_label' => number_format($commissionBasisPoints / 100, 2) . '% of platform-processed sales',
            'card_label' => number_format($cardBasisPoints / 100, 2) . '% + $' . number_format($cardFixedCents / 100, 2),
        ];
    }

    private function payoutExample(int $saleCents, array $economics): string
    {
        $commission = (int) round($saleCents * (((int) $economics['commission_basis_points']) / 10000));
        $card = (int) round($saleCents * (((int) $economics['card_basis_points']) / 10000)) + (int) $economics['card_fixed_cents'];
        return $this->plainMoney(max(0, $saleCents - $commission - $card));
    }

    private function plainMoney(int $cents): string
    {
        return '$' . number_format($cents / 100, 2);
    }

    private function isComplementary(TenantContext $tenant): bool
    {
        if (!$this->columnExists('tenants', 'complementary')) {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT complementary FROM tenants WHERE id = :tenant_id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenant->tenantId]);
        return (int) $stmt->fetchColumn() === 1;
    }

    private function status(float $used, float $limit): string
    {
        if ($limit <= 0 && $used > 0) {
            return 'Upgrade required';
        }
        if ($limit > 0 && $used >= $limit) {
            return 'At or over limit';
        }
        if ($limit > 0 && $used >= $limit * 0.8) {
            return 'Near limit';
        }
        return 'OK';
    }

    private function countRows(string $table, TenantContext $tenant): int
    {
        if (!$this->tableExists($table)) {
            return 0;
        }
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE tenant_id = :tenant_id");
            $stmt->execute(['tenant_id' => $tenant->tenantId]);
            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function countMemberships(TenantContext $tenant): int
    {
        foreach (['tenant_memberships', 'memberships'] as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            try {
                $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE tenant_id = :tenant_id");
                $stmt->execute(['tenant_id' => $tenant->tenantId]);
                return (int) $stmt->fetchColumn();
            } catch (Throwable) {
            }
        }
        return 0;
    }

    private function storageGb(TenantContext $tenant): float
    {
        foreach (['media_assets', 'media'] as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            foreach (['size_bytes', 'bytes', 'file_size'] as $column) {
                if (!$this->columnExists($table, $column)) {
                    continue;
                }
                $stmt = $this->pdo->prepare("SELECT COALESCE(SUM({$column}),0) FROM {$table} WHERE tenant_id = :tenant_id");
                $stmt->execute(['tenant_id' => $tenant->tenantId]);
                return round(((float) $stmt->fetchColumn()) / 1024 / 1024 / 1024, 2);
            }
        }
        return 0.0;
    }

    private function setting(TenantContext $tenant, string $key, string $default = ''): string
    {
        if (!$this->tableExists('tenant_settings')) {
            return $default;
        }
        $stmt = $this->pdo->prepare('SELECT setting_value FROM tenant_settings WHERE tenant_id = :tenant_id AND setting_key = :setting_key LIMIT 1');
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'setting_key' => $key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string) $value;
    }

    private function setSetting(TenantContext $tenant, string $key, string $value): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, updated_at) VALUES (:tenant_id, :setting_key, :setting_value, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP');
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'setting_key' => $key, 'setting_value' => $value]);
    }

    private function validCsrf(string $token): bool
    {
        return isset($_SESSION['csrf_token']) && hash_equals((string) $_SESSION['csrf_token'], $token);
    }

    private function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['csrf_token'];
    }

    private function money(int $cents): string
    {
        return $cents === 0 ? '$0' : '$' . number_format($cents / 100, 2) . ' / month';
    }

    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->pdo->query('SHOW TABLES LIKE ' . $this->pdo->quote($table));
            return (bool) ($stmt && $stmt->fetchColumn());
        } catch (Throwable) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM {$table} LIKE " . $this->pdo->quote($column));
            return (bool) ($stmt && $stmt->fetchColumn());
        } catch (Throwable) {
            return false;
        }
    }

    private function truthy(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
