#!/bin/bash
# Applies signup-code revocation and existing-tenant free-access redemption updates.
# Run from the ArtsFolio repository root on the workstation.

set -euo pipefail

if [[ ! -f app/Platform/Signup/SignupCodeRepository.php || ! -f public/index.php ]]; then
  echo "Run this script from the ArtsFolio repository root." >&2
  exit 1
fi

python3 <<'PY'
from pathlib import Path

repo = Path('.')

def read(path: str) -> str:
    return (repo / path).read_text()

def write(path: str, text: str) -> None:
    (repo / path).parent.mkdir(parents=True, exist_ok=True)
    (repo / path).write_text(text)

# Signup code repository: revoke any code and validate free-month code redemption for existing tenants.
path = 'app/Platform/Signup/SignupCodeRepository.php'
text = read(path)
if 'public function revoke(int $codeId): void' not in text:
    marker = '    public function markRedeemed(int $codeId, int $tenantId, string $email): void\n'
    if marker not in text:
        raise SystemExit(f'Missing expected markRedeemed marker in {path}')
    insertion = '''    /**
     * Revokes any signup code type so it cannot be used again.
     */
    public function revoke(int $codeId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE tenant_signup_codes
             SET status = 'revoked', updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND status <> 'revoked'"
        );
        $stmt->execute(['id' => $codeId]);
    }

    /**
     * Validates a free-access code for use by an existing tenant from billing.
     */
    public function validateFreeAccessForExistingTenant(string $code, string $email): array
    {
        $row = $this->validateForSignup($code, $email);
        if ((string) ($row['code_type'] ?? '') !== 'free_months') {
            throw new RuntimeException('Only free access signup codes can be applied from tenant billing.');
        }
        if ((int) ($row['free_access_months'] ?? 0) < 1) {
            throw new RuntimeException('Free access code does not include a free-month grant.');
        }

        return $row;
    }

'''
    text = text.replace(marker, insertion + marker)
write(path, text)

# Platform signup-code admin: add revoke form/action/notice.
path = 'app/Http/Controllers/Platform/Admin/SignupCodesController.php'
text = read(path)
if '/platform/admin/signup-codes/revoke' not in text:
    marker = '''        </form>
    </td>
</tr>
HTML;
'''
    replacement = '''        </form>
        <form method="post" action="/platform/admin/signup-codes/revoke" onsubmit="return confirm('Revoke this signup code? It cannot be used after revocation.');">
            <input type="hidden" name="csrf_token" value="{$csrf}">
            <input type="hidden" name="id" value="{$id}">
            <button type="submit" class="button-link-danger">Revoke</button>
        </form>
    </td>
</tr>
HTML;
'''
    if marker not in text:
        raise SystemExit(f'Missing signup-code invite form marker in {path}')
    text = text.replace(marker, replacement, 1)
if 'public function revoke(Request $request' not in text:
    marker = '    private function bulkCreate(Request $request, ?array $currentUser): void\n'
    if marker not in text:
        raise SystemExit(f'Missing bulkCreate marker in {path}')
    insertion = '''    public function revoke(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1) {
            return Response::html('<h1>Invalid signup code</h1>', 422);
        }

        $this->codes->revoke($id);
        $this->audit($request, $currentUser, 'platform.signup_code.revoked', (string) $id, []);

        return new Response('', 303, ['Location' => '/platform/admin/signup-codes?notice=revoked']);
    }

'''
    text = text.replace(marker, insertion + marker)
if "'revoked' => 'Signup code revoked.'" not in text:
    text = text.replace("            'invite-queued' => 'Signup invite email queued.',\n", "            'invite-queued' => 'Signup invite email queued.',\n            'revoked' => 'Signup code revoked.',\n")
write(path, text)

# Tenant billing: allow applying free-month code to existing tenant.
path = 'app/Http/Controllers/Tenant/Admin/BillingController.php'
text = read(path)
if 'use App\\Platform\\Signup\\SignupCodeRepository;' not in text:
    text = text.replace('use App\\Platform\\Tenancy\\TenantContext;\n', 'use App\\Platform\\Tenancy\\TenantContext;\nuse App\\Platform\\Signup\\SignupCodeRepository;\n')
if '$freeAccessPlanOptions = $this->freeAccessPlanOptions' not in text:
    marker = '''        $complementary = $this->isComplementary($tenant) ? '<p class="admin-notice admin-notice-info"><strong>Complementary plan:</strong> platform service billing is waived. Platform commission and credit card charges still apply to sales.</p>' : '';
        $csrf = $this->e($this->csrfToken());
        $planName = $this->e((string) $plan['name']);
'''
    replacement = '''        $complementary = $this->isComplementary($tenant) ? '<p class="admin-notice admin-notice-info"><strong>Complementary plan:</strong> platform service billing is waived. Platform commission and credit card charges still apply to sales.</p>' : '';
        $csrf = $this->e($this->csrfToken());
        $freeAccessPlanOptions = $this->freeAccessPlanOptions($plans, (string) $plan['slug']);
        $planName = $this->e((string) $plan['name']);
'''
    if marker not in text:
        raise SystemExit(f'Missing billing variable marker in {path}')
    text = text.replace(marker, replacement, 1)
if '/admin/billing/free-access-code' not in text:
    marker = '''  <div class="admin-panel">
    <p class="admin-muted">Change plan</p>
    <form class="plan-edit-form" method="post" action="/admin/billing/plan" class="admin-form">
      <input type="hidden" name="csrf_token" value="{$csrf}">
      <label>Plan<select name="plan_slug">{$planOptions}</select></label>
      <button type="submit">Update plan</button>
    </form>
    <p class="admin-muted">Upgrade and downgrade changes are recorded immediately. External billing collection remains a platform operations task until subscription billing is connected.</p>
  </div>
</section>
'''
    replacement = '''  <div class="admin-panel">
    <p class="admin-muted">Change plan</p>
    <form class="plan-edit-form" method="post" action="/admin/billing/plan" class="admin-form">
      <input type="hidden" name="csrf_token" value="{$csrf}">
      <label>Plan<select name="plan_slug">{$planOptions}</select></label>
      <button type="submit">Update plan</button>
    </form>
    <p class="admin-muted">Upgrade and downgrade changes are recorded immediately. External billing collection remains a platform operations task until subscription billing is connected.</p>
  </div>
  <div class="admin-panel">
    <p class="admin-muted">Apply free access code</p>
    <form method="post" action="/admin/billing/free-access-code" class="admin-form">
      <input type="hidden" name="csrf_token" value="{$csrf}">
      <label>Free access code<input type="text" name="signup_code" autocomplete="off" required></label>
      <label>Plan for free access<select name="plan_slug" required>{$freeAccessPlanOptions}</select></label>
      <button type="submit">Apply free access</button>
    </form>
    <p class="admin-muted">Free access codes can move this tenant onto any active plan for the number of months configured by platform admin. Card fees, commissions, shipping, and taxes are not waived.</p>
  </div>
</section>
'''
    if marker not in text:
        raise SystemExit(f'Missing billing change-plan panel marker in {path}')
    text = text.replace(marker, replacement, 1)
if 'public function applyFreeAccessCode' not in text:
    marker = '    /**\n     * Count billable custom-domain groups for plan usage.\n     */\n'
    if marker not in text:
        raise SystemExit(f'Missing billing countCustomDomainGroups marker in {path}')
    insertion = '''    public function applyFreeAccessCode(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'owner'])) {
            return Response::html('<h1>Forbidden</h1><p>Only tenant owners may apply billing codes.</p>', 403);
        }
        if (!$this->validCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }

        $code = strtoupper(trim((string) ($_POST['signup_code'] ?? '')));
        $planSlug = strtolower(trim((string) ($_POST['plan_slug'] ?? '')));
        $email = strtolower(trim((string) ($currentUser['email'] ?? '')));
        if ($email === '') {
            return Response::html('<h1>Current user email unavailable</h1>', 422);
        }

        try {
            $codes = new SignupCodeRepository($this->pdo);
            $signupCode = $codes->validateFreeAccessForExistingTenant($code, $email);
            $plan = $this->planBySlug($planSlug);
            if (!$plan) {
                return Response::html('<h1>Invalid plan</h1>', 422);
            }
            $this->applyFreeAccessPlan($tenant, (int) $plan['id'], $signupCode);
            $codes->markRedeemed((int) $signupCode['id'], $tenant->tenantId, $email);
            $this->setSetting($tenant, 'billing_plan', (string) $plan['slug']);
        } catch (Throwable $e) {
            return Response::html('<h1>Could not apply free access code</h1><p>' . $this->e($e->getMessage()) . '</p>', 422);
        }

        FlashMessages::success('Free access code applied.');
        return new Response('', 303, ['Location' => '/admin/billing?notice=free-access-applied']);
    }

'''
    text = text.replace(marker, insertion + marker)
if 'private function freeAccessPlanOptions' not in text:
    marker = '    private function currentPlan(TenantContext $tenant): ?array\n'
    if marker not in text:
        raise SystemExit(f'Missing currentPlan marker in {path}')
    insertion = '''    private function freeAccessPlanOptions(array $plans, string $currentSlug): string
    {
        $options = '';
        foreach ($plans as $plan) {
            $slug = $this->e((string) $plan['slug']);
            $name = $this->e((string) $plan['name']);
            $price = $this->money((int) ($plan['monthly_price_cents'] ?? 0));
            $selected = (string) $plan['slug'] === $currentSlug ? ' selected' : '';
            $options .= '<option value="' . $slug . '"' . $selected . '>' . $name . ' — ' . $price . '</option>';
        }

        return $options;
    }

'''
    text = text.replace(marker, insertion + marker)
if 'private function applyFreeAccessPlan' not in text:
    marker = '    private function fallbackPlan(): array\n'
    if marker not in text:
        raise SystemExit(f'Missing fallbackPlan marker in {path}')
    insertion = '''    private function applyFreeAccessPlan(TenantContext $tenant, int $planId, array $signupCode): void
    {
        if (!$this->tableExists('tenant_plan_assignments')) {
            return;
        }

        $months = max(1, (int) ($signupCode['free_access_months'] ?? 0));
        $complimentaryUntil = (new \\DateTimeImmutable('now'))->modify('+' . $months . ' months')->format('Y-m-d H:i:s');
        $billingNote = 'Free access signup code ' . (string) $signupCode['code'] . ' applied from tenant billing for ' . $months . ' month' . ($months === 1 ? '' : 's') . '.';

        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_plan_assignments (tenant_id, plan_id, status, complimentary_until, granted_by_signup_code_id, billing_note, created_at)
             VALUES (:tenant_id, :plan_id, "trial", :complimentary_until, :signup_code_id, :billing_note, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE
                plan_id = VALUES(plan_id),
                status = "trial",
                complimentary_until = VALUES(complimentary_until),
                granted_by_signup_code_id = VALUES(granted_by_signup_code_id),
                billing_note = VALUES(billing_note)'
        );
        $stmt->execute([
            'tenant_id' => $tenant->tenantId,
            'plan_id' => $planId,
            'complimentary_until' => $complimentaryUntil,
            'signup_code_id' => (int) $signupCode['id'],
            'billing_note' => $billingNote,
        ]);
    }

'''
    text = text.replace(marker, insertion + marker)
write(path, text)

# Routes.
path = 'public/index.php'
text = read(path)
route = "    $router->post('/admin/billing/free-access-code', fn (Request $request): Response => (new TenantAdminBillingController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo))->applyFreeAccessCode($request, $tenant, $currentUser));\n"
if "/admin/billing/free-access-code" not in text:
    marker = "    $router->post('/admin/billing/plan', fn (Request $request): Response => (new TenantAdminBillingController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo))->updatePlan($request, $tenant, $currentUser));\n"
    if marker not in text:
        raise SystemExit(f'Missing tenant billing plan route marker in {path}')
    text = text.replace(marker, marker + route)
route = "    $router->post('/platform/admin/signup-codes/revoke', fn (Request $request): Response => (new PlatformAdminSignupCodesController(new RequirePlatformRole(new MembershipRepository($pdo)), new SignupCodeRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo), new App\\Platform\\Email\\EmailOutboxRepository($pdo)))->revoke($request, $currentUser));\n"
if "/platform/admin/signup-codes/revoke" not in text:
    marker = "    $router->post('/platform/admin/signup-codes/send', fn (Request $request): Response => (new PlatformAdminSignupCodesController(new RequirePlatformRole(new MembershipRepository($pdo)), new SignupCodeRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo), new App\\Platform\\Email\\EmailOutboxRepository($pdo)))->send($request, $currentUser));\n"
    if marker not in text:
        raise SystemExit(f'Missing platform signup-code send route marker in {path}')
    text = text.replace(marker, marker + route)
write(path, text)

# Static regression test.
write('scripts/test/signup_code_revocation_and_billing_static.php', '''<?php

declare(strict_types=1);

/**
 * Static regression checks for signup-code revocation and billing redemption.
 */

$root = dirname(__DIR__, 2);
$files = [
    'repository' => $root . '/app/Platform/Signup/SignupCodeRepository.php',
    'signupCodesController' => $root . '/app/Http/Controllers/Platform/Admin/SignupCodesController.php',
    'billingController' => $root . '/app/Http/Controllers/Tenant/Admin/BillingController.php',
    'routes' => $root . '/public/index.php',
];

foreach ($files as $name => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$name} file: {$path}\n");
        exit(1);
    }
    $files[$name] = file_get_contents($path) ?: '';
}

$checks = [
    'repository exposes revoke method' => str_contains($files['repository'], 'public function revoke(int $codeId): void'),
    'repository validates free code for existing tenant' => str_contains($files['repository'], 'validateFreeAccessForExistingTenant'),
    'repository limits tenant billing redemption to free codes' => str_contains($files['repository'], 'Only free access signup codes can be applied from tenant billing.'),
    'platform admin controller has revoke action' => str_contains($files['signupCodesController'], 'public function revoke(Request $request'),
    'platform admin page posts revoke route' => str_contains($files['signupCodesController'], '/platform/admin/signup-codes/revoke'),
    'platform revoke route is mounted' => str_contains($files['routes'], "/platform/admin/signup-codes/revoke"),
    'tenant billing controller has free access action' => str_contains($files['billingController'], 'public function applyFreeAccessCode'),
    'tenant billing form posts free access route' => str_contains($files['billingController'], '/admin/billing/free-access-code'),
    'tenant billing redemption writes complimentary_until' => str_contains($files['billingController'], 'complimentary_until'),
    'tenant billing redemption marks code redeemed' => str_contains($files['billingController'], 'markRedeemed((int) $signupCode'),
    'tenant billing route is mounted' => str_contains($files['routes'], "/admin/billing/free-access-code"),
];

$failed = [];
foreach ($checks as $label => $ok) {
    if (!$ok) {
        $failed[] = $label;
    }
}

if ($failed !== []) {
    fwrite(STDERR, "Failed signup-code revocation/billing static checks:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo "Signup-code revocation and tenant billing redemption wiring is present.\n";

// End of file.
''')

# Preflight hook.
path = 'scripts/test/preflight.sh'
if (repo / path).exists():
    text = read(path)
    if 'signup_code_revocation_and_billing_static.php' not in text:
        line = 'php scripts/test/signup_code_revocation_and_billing_static.php\n'
        if 'php scripts/test/signup_code_free_plan_static.php\n' in text:
            text = text.replace('php scripts/test/signup_code_free_plan_static.php\n', 'php scripts/test/signup_code_free_plan_static.php\n' + line)
        else:
            text = text.rstrip() + '\n' + line
        write(path, text)

# Docs.
docs = {
    'docs/admin/signup-code-revocation-and-billing-redemption.md': ('Signup code revocation and tenant billing redemption', '''- Platform admins may revoke any signup code from Platform Admin -> Signup Codes.
- Revoked codes cannot start public signup and cannot be applied from tenant billing.
- Tenant owners may apply a free access code from Tenant Admin -> Billing.
- Existing-tenant redemption accepts only `free_months` codes and requires the selected plan to be active.
- Applying the code updates the tenant plan assignment to `trial`, sets `complimentary_until`, links `granted_by_signup_code_id`, and records a billing note.
- Free access waives only ArtsFolio platform subscription billing for the configured period. Sales commissions, card fees, shipping, and taxes still apply.
'''),
    'docs/dev/signup-code-revocation-and-billing-redemption.md': ('Developer notes: signup code revocation and billing redemption', '''- `SignupCodeRepository::revoke()` sets `tenant_signup_codes.status = revoked`.
- `SignupCodeRepository::validateFreeAccessForExistingTenant()` reuses normal active/recipient/max-redemption validation, then enforces `code_type = free_months`.
- `Tenant\\Admin\\BillingController::applyFreeAccessCode()` applies an active free-month code to an existing tenant plan assignment.
- Existing-tenant free access writes `tenant_plan_assignments.status = trial`, `complimentary_until`, `granted_by_signup_code_id`, and `billing_note`.
- Routes are mounted at `/platform/admin/signup-codes/revoke` and `/admin/billing/free-access-code`.
- Static coverage lives in `scripts/test/signup_code_revocation_and_billing_static.php`.
'''),
    'docs/user/free-access-code-existing-tenant.md': ('Using a free access code on an existing site', '''- Sign in to your tenant admin area.
- Open Billing.
- Enter the free access code supplied by ArtsFolio.
- Choose the plan to use during the free access period.
- Submit the form.

The code changes the site to the selected plan for the number of free months configured by ArtsFolio. It does not waive transaction fees, platform sales commissions, shipping, or taxes.
'''),
}
for path, (title, body) in docs.items():
    write(path, f'# {title}\n\n{body}\n<!-- End of file. -->\n')

# Project state append only. Do not try to patch by line number.
path = 'PROJECT_STATE.md'
if (repo / path).exists():
    text = read(path)
    section = '''

## Signup code revocation and existing-tenant free access

- Platform admins can revoke any tenant signup code type from Platform Admin -> Signup Codes.
- Revoked codes are blocked by the same active-code validation used for public signup and tenant billing redemption.
- Tenant owners can apply `free_months` codes from Tenant Admin -> Billing to an existing tenant.
- Existing-tenant free access updates `tenant_plan_assignments` to the selected active plan with `status = trial`, `complimentary_until`, `granted_by_signup_code_id`, and `billing_note`.
- Existing-tenant code redemption increments `tenant_signup_codes.redemption_count` and can move the code to `redeemed` when its redemption limit is reached.

<!-- End of file. -->
'''
    if '## Signup code revocation and existing-tenant free access' not in text:
        text = text.rstrip()
        if text.endswith('<!-- End of file. -->'):
            text = text[:-len('<!-- End of file. -->')].rstrip()
        write(path, text + section)
PY

php -l app/Platform/Signup/SignupCodeRepository.php
php -l app/Http/Controllers/Platform/Admin/SignupCodesController.php
php -l app/Http/Controllers/Tenant/Admin/BillingController.php
php -l public/index.php
php -l scripts/test/signup_code_revocation_and_billing_static.php
php scripts/test/signup_code_revocation_and_billing_static.php

echo "Signup code revocation and tenant billing free-access redemption update applied."

# End of file.
