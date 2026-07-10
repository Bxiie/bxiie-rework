<?php

declare(strict_types=1);

namespace App\Platform\Signup;

use App\Platform\Settings\PlatformSettingsRepository;
use PDO;
use RuntimeException;

/**
 * Creates a tenant from the public platform signup flow.
 *
 * This service is deliberately schema-tolerant while the platform refactor is
 * still settling. It inserts only columns that exist, so the same signup flow
 * can run against local and production databases during the transition.
 */
final class TenantSignupService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?PlatformSettingsRepository $settings = null,
        private readonly ?SignupCodeRepository $signupCodes = null,
    ) {
    }


    /**
     * Normalizes and validates tenant slugs before persistence or domain use.
     */
    private function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])$/', $slug)) {
            throw new RuntimeException('Site address must be 3 to 63 lowercase letters, numbers, or hyphens and cannot begin or end with a hyphen.');
        }

        return $slug;
    }

    public function requiresSignupCode(): bool
    {
        return $this->settings !== null
            && in_array(strtolower((string) $this->settings->get('tenant_signup_code_required', '0')), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Validates a signup passcode before the site details form is displayed.
     */
    public function validateSignupEntryCode(string $signupCode): ?array
    {
        $signupCode = strtoupper(trim($signupCode));

        if (!$this->requiresSignupCode() && $signupCode === '') {
            return null;
        }
        if ($this->signupCodes === null) {
            throw new RuntimeException('Signup code validation is not configured.');
        }
        if ($signupCode === '') {
            throw new RuntimeException('A signup passcode is required to create a new site.');
        }

        return $this->signupCodes->validateForEntry($signupCode);
    }

    public function register(
        string $slug,
        string $siteName,
        string $adminEmail,
        string $adminName,
        string $passwordHash,
        string $platformDomain = 'artsfol.io',
        ?string $signupCode = null,
        ?int $existingUserId = null,
        bool $createPasswordIdentity = true,
        ?string $selectedPlanSlug = null,
    ): array {
        $slug = $this->normalizeSlug($slug);
        $siteName = trim($siteName);
        $adminEmail = strtolower(trim($adminEmail));
        $adminName = trim($adminName) !== '' ? trim($adminName) : $adminEmail;

        if ($slug === '') {
            throw new RuntimeException('Tenant slug is required.');
        }

        if ($siteName === '') {
            throw new RuntimeException('Site name is required.');
        }

        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('A valid email address is required.');
        }

        $domain = $slug . '.' . $platformDomain;
        $validatedSignupCode = $this->validateSignupCode($signupCode, $adminEmail);
        $selectedPlan = $this->validateSelectedPlan($validatedSignupCode, $selectedPlanSlug);

        $this->pdo->beginTransaction();

        try {
            $this->ensureTenantSlugAvailable($slug);
            $this->ensureDomainAvailable($domain);

            $tenantId = $this->createTenant($slug, $siteName);
            $userId = $existingUserId !== null && $existingUserId > 0
                ? $this->updateExistingUser($existingUserId, $adminEmail, $adminName)
                : $this->createOrUpdateUser($adminEmail, $adminName);

            if ($createPasswordIdentity) {
                $this->createOrUpdatePasswordIdentity($userId, $adminEmail, $passwordHash);
            }

            $this->createTenantDomain($tenantId, $domain, true);
            $this->createTenantMembership($tenantId, $userId);
            $this->seedTenantCss($tenantId);
            $this->assignTenantAdminRole($tenantId, $userId);
            $this->queueProvisioningJobs($tenantId, $domain);
            $this->assignSignupCodePlanGrant($tenantId, $validatedSignupCode, $selectedPlan);
            $this->queueLifecycleEmail($tenantId, $userId, $adminEmail, $adminName, $slug);
            if ($validatedSignupCode !== null && $this->signupCodes !== null) {
                $this->signupCodes->markRedeemed((int) $validatedSignupCode['id'], $tenantId, $adminEmail);
            }

            $this->pdo->commit();

            return [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'slug' => $slug,
                'domain' => $domain,
                'login_url' => 'https://' . $domain . '/login',
                'selected_plan' => $selectedPlan ?? [],
                'selected_plan_slug' => (string) ($selectedPlan['slug'] ?? 'free'),
                'selected_plan_monthly_price_cents' => (int) ($selectedPlan['monthly_price_cents'] ?? 0),
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }


    /**
     * Lists active pricing plans for public signup plan selection.
     */
    public function activePlans(): array
    {
        if ($this->signupCodes !== null) {
            return $this->signupCodes->listActivePlans();
        }
        if (!$this->tableExists('plans')) {
            return [];
        }

        $stmt = $this->pdo->query(
            "SELECT id, slug, name, monthly_price_cents, description, stripe_product_id, stripe_monthly_price_id, stripe_price_lookup_key
             FROM plans
             WHERE is_active = 1
             ORDER BY monthly_price_cents ASC, id ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Enforces optional platform signup-code gating before tenant creation.
     */
    private function validateSignupCode(?string $signupCode, string $adminEmail): ?array
    {
        $required = $this->settings !== null
            && in_array(strtolower((string) $this->settings->get('tenant_signup_code_required', '0')), ['1', 'true', 'yes', 'on'], true);
        $signupCode = strtoupper(trim((string) $signupCode));

        if (!$required && $signupCode === '') {
            return null;
        }
        if ($this->signupCodes === null) {
            throw new RuntimeException('Signup code validation is not configured.');
        }
        if ($signupCode === '') {
            throw new RuntimeException('A signup passcode is required to create a new site.');
        }

        return $this->signupCodes->validateForSignup($signupCode, $adminEmail);
    }

    /**
     * Validates the selected public signup plan.
     */
    private function validateSelectedPlan(?array $signupCode, ?string $selectedPlanSlug): ?array
    {
        $selectedPlanSlug = strtolower(trim((string) $selectedPlanSlug));
        if ($selectedPlanSlug === '') { $selectedPlanSlug = 'free'; }
        if (!$this->tableExists('plans')) { return null; }
        $stmt = $this->pdo->prepare('SELECT id, slug, name, monthly_price_cents, description, stripe_product_id, stripe_monthly_price_id, stripe_price_lookup_key FROM plans WHERE slug = :slug AND is_active = 1 LIMIT 1');
        $stmt->execute(['slug' => $selectedPlanSlug]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$plan) { throw new RuntimeException('Selected plan is not available.'); }
        return $plan;
    }

    /**
     * Assigns the selected plan     * Assigns the selected plan and records the free-access end date.
     */
    private function assignSignupCodePlanGrant(int $tenantId, ?array $signupCode, ?array $selectedPlan): void
    {
        if ($selectedPlan === null || !$this->tableExists('tenant_plan_assignments')) { return; }
        $isFreeAccess = $signupCode !== null && (string) ($signupCode['code_type'] ?? '') === 'free_months';
        $months = $isFreeAccess ? max(1, (int) ($signupCode['free_access_months'] ?? 0)) : 0;
        $complimentaryUntil = $isFreeAccess ? (new \DateTimeImmutable('now'))->modify('+' . $months . ' months')->format('Y-m-d H:i:s') : null;
        $priceCents = (int) ($selectedPlan['monthly_price_cents'] ?? 0);
        $billingStatus = $priceCents > 0 && !$isFreeAccess ? 'payment_pending' : ($isFreeAccess ? 'trial' : 'free');
        $status = $isFreeAccess ? 'trial' : 'active';
        $note = $isFreeAccess ? 'Free access signup code ' . (string) $signupCode['code'] . ' for ' . $months . ' month' . ($months === 1 ? '' : 's') . '.' : ($priceCents > 0 ? 'Paid signup selected. Stripe Checkout is required before billing is complete.' : 'Free plan selected at signup.');
        $values = ['tenant_id' => $tenantId, 'plan_id' => (int) $selectedPlan['id'], 'status' => $status, 'billing_status' => $billingStatus, 'current_period_started_at' => $this->now(), 'current_period_ends_at' => (new \DateTimeImmutable('now'))->modify('+1 month')->format('Y-m-d H:i:s'), 'complimentary_until' => $complimentaryUntil, 'granted_by_signup_code_id' => $isFreeAccess ? (int) $signupCode['id'] : null, 'billing_note' => $note, 'created_at' => $this->now()];
        $stmt = $this->pdo->prepare('SELECT id FROM tenant_plan_assignments WHERE tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId]);
        $existingId = $stmt->fetchColumn();
        if ($existingId !== false) { $this->updateKnown('tenant_plan_assignments', (int) $existingId, $values); return; }
        $this->insertKnown('tenant_plan_assignments', $values);
    }

    private function ensureDomainAvailable(string $domain): void
    {
        if (!$this->tableExists('tenant_domains')) {
            return;
        }

        $columns = $this->tableColumns('tenant_domains');
        $column = isset($columns['hostname']) ? 'hostname' : 'domain';

        $stmt = $this->pdo->prepare("SELECT id FROM tenant_domains WHERE {$column} = :domain LIMIT 1");
        $stmt->execute(['domain' => $domain]);

        if ($stmt->fetchColumn() !== false) {
            throw new RuntimeException('Tenant domain is already in use.');
        }
    }

    private function createTenant(string $slug, string $siteName): int
    {
        $this->insertKnown('tenants', [
            'uuid' => $this->uuid(),
            'slug' => $slug,
            'name' => $siteName,
            'status' => 'active',
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function updateExistingUser(int $userId, string $email, string $displayName): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);

        if ($stmt->fetchColumn() === false) {
            throw new RuntimeException('OAuth user could not be found during tenant signup.');
        }

        $this->updateKnown('users', $userId, [
            'email' => $email,
            'display_name' => $displayName,
            'status' => 'active',
            'updated_at' => $this->now(),
        ]);

        return $userId;
    }

    private function createOrUpdateUser(string $email, string $name): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $existingId = $stmt->fetchColumn();

        if ($existingId !== false) {
            $this->updateKnown('users', (int) $existingId, [
                'name' => $name,
                'display_name' => $name,
                'status' => 'active',
                'updated_at' => $this->now(),
            ]);

            return (int) $existingId;
        }

        $this->insertKnown('users', [
            'uuid' => $this->uuid(),
            'email' => $email,
            'name' => $name,
            'display_name' => $name,
            'status' => 'active',
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function createOrUpdatePasswordIdentity(int $userId, string $email, string $passwordHash): void
    {
        if (!$this->tableExists('user_identities')) {
            return;
        }

        $columns = $this->tableColumns('user_identities');

        $candidateProviderColumns = ['provider', 'identity_provider', 'type'];
        $providerColumn = null;
        foreach ($candidateProviderColumns as $column) {
            if (isset($columns[$column])) {
                $providerColumn = $column;
                break;
            }
        }

        $candidateIdentifierColumns = ['provider_user_id', 'identifier', 'email'];
        $identifierColumn = null;
        foreach ($candidateIdentifierColumns as $column) {
            if (isset($columns[$column])) {
                $identifierColumn = $column;
                break;
            }
        }

        $hashColumn = isset($columns['password_hash']) ? 'password_hash' : null;

        if ($providerColumn === null || $identifierColumn === null || $hashColumn === null) {
            return;
        }

        $stmt = $this->pdo->prepare(
            "SELECT id FROM user_identities
             WHERE user_id = :user_id
               AND {$providerColumn} = 'password'
             LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId]);
        $existingId = $stmt->fetchColumn();

        $values = [
            'uuid' => $this->uuid(),
            'user_id' => $userId,
            $providerColumn => 'password',
            $identifierColumn => $email,
            $hashColumn => $passwordHash,
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ];

        if ($existingId !== false) {
            $this->updateKnown('user_identities', (int) $existingId, $values);
            return;
        }

        $this->insertKnown('user_identities', $values);
    }

    private function createTenantDomain(int $tenantId, string $domain, bool $primary): void
    {
        if (!$this->tableExists('tenant_domains')) {
            return;
        }

        $domainType = str_ends_with($domain, '.artsfol.io') ? 'platform_subdomain' : 'custom';

        $this->insertKnown('tenant_domains', [
            'uuid' => $this->uuid(),
            'tenant_id' => $tenantId,
            'hostname' => $domain,
            'domain' => $domain,
            'status' => 'active',
            'domain_type' => $domainType,
            'is_primary_domain' => $primary ? 1 : 0,
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ]);
    }

    private function createTenantMembership(int $tenantId, int $userId): void
    {
        if (!$this->tableExists('tenant_memberships')) {
            return;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO tenant_memberships (
                tenant_id,
                user_id,
                status,
                created_at,
                updated_at
            ) VALUES (
                :tenant_id,
                :user_id,
                'active',
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                status = 'active',
                updated_at = CURRENT_TIMESTAMP"
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
        ]);
    }

    private function assignTenantAdminRole(int $tenantId, int $userId): void
    {
        if (!$this->tableExists('roles') || !$this->tableExists('role_assignments')) {
            return;
        }

        $roleId = $this->ensureTenantOwnerRoleId();

        $stmt = $this->pdo->prepare(
            "INSERT IGNORE INTO role_assignments (
                role_id,
                user_id,
                tenant_id,
                created_at
            ) VALUES (
                :role_id,
                :user_id,
                :tenant_id,
                CURRENT_TIMESTAMP
            )"
        );

        $stmt->execute([
            'role_id' => $roleId,
            'user_id' => $userId,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Resolves or recreates the canonical tenant owner role.
     *
     * Signup cannot depend on historical seed data still being present. Older
     * test and repair work has occasionally left production without the tenant
     * owner/admin role, so tenant creation self-heals the role row before
     * assigning ownership to the new site creator.
     */
    private function ensureTenantOwnerRoleId(): int
    {
        $roleId = $this->findTenantRoleId(['owner', 'tenant_owner', 'admin', 'tenant_admin', 'manager']);

        if ($roleId !== null) {
            return $roleId;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO roles (
                scope,
                slug,
                name,
                description,
                created_at
            ) VALUES (
                'tenant',
                'owner',
                'Tenant Owner',
                'Full tenant ownership.',
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description)"
        );
        $stmt->execute();

        $roleId = $this->findTenantRoleId(['owner']);

        if ($roleId === null) {
            throw new RuntimeException('Tenant owner role could not be created for new tenant owner assignment.');
        }

        return $roleId;
    }

    private function queueProvisioningJobs(int $tenantId, string $domain): void
    {
        if (!$this->tableExists('background_jobs')) {
            return;
        }

        foreach ([
            'custom_domain.verify_dns' => ['hostname' => $domain],
            'tenant.site.bootstrap' => ['domain' => $domain],
        ] as $jobType => $payload) {
            $this->insertKnown('background_jobs', [
                'tenant_id' => $tenantId,
                'job_type' => $jobType,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'status' => 'queued',
                'available_at' => $this->now(),
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ]);
        }
    }

    /**
     * Queues tenant lifecycle emails with direct admin, help, tour, and training links.
     */
    private function queueLifecycleEmail(int $tenantId, int $userId, string $email, string $name, string $slug): void
    {
        if (!$this->tableExists('email_outbox')) {
            return;
        }

        $platformUrl = rtrim((string) (getenv('ARTSFOLIO_PUBLIC_URL') ?: 'https://artsfol.io'), '/');
        $tenantHost = (string) preg_replace('/[^a-z0-9-]/', '', strtolower($slug));
        $tenantBaseUrl = 'https://' . $tenantHost . '.artsfol.io';
        $adminUrl = $tenantBaseUrl . '/admin';
        $tourUrl = $tenantBaseUrl . '/admin/getting-started';
        $helpUrl = $platformUrl . '/help';
        $functionsUrl = $platformUrl . '/help/tenant-admin-functions';
        $videosUrl = $platformUrl . '/help/training-videos';
        $safeName = htmlspecialchars($name !== '' ? $name : 'there', ENT_QUOTES, 'UTF-8');
        $safeSlug = htmlspecialchars($slug, ENT_QUOTES, 'UTF-8');
        $safeAdminUrl = htmlspecialchars($adminUrl, ENT_QUOTES, 'UTF-8');
        $safeTourUrl = htmlspecialchars($tourUrl, ENT_QUOTES, 'UTF-8');
        $safeHelpUrl = htmlspecialchars($helpUrl, ENT_QUOTES, 'UTF-8');
        $safeFunctionsUrl = htmlspecialchars($functionsUrl, ENT_QUOTES, 'UTF-8');
        $safeVideosUrl = htmlspecialchars($videosUrl, ENT_QUOTES, 'UTF-8');

        $schedule = [
            ['tenant_admin_welcome_6h', 'Welcome to ArtsFolio', 21600, "Welcome to ArtsFolio, {$name}.\n\nOpen your admin:\n{$adminUrl}\n\nStart the guided setup tour:\n{$tourUrl}\n\nHelp: {$helpUrl}\nFunction index: {$functionsUrl}\nTraining videos: {$videosUrl}\n", "<p>Welcome to ArtsFolio, {$safeName}.</p><p>Your tenant <strong>{$safeSlug}</strong> is ready.</p><p><a href=\"{$safeAdminUrl}\">Open your admin</a></p><p><a href=\"{$safeTourUrl}\">Start the guided setup tour</a></p><ul><li><a href=\"{$safeHelpUrl}\">Help index</a></li><li><a href=\"{$safeFunctionsUrl}\">Tenant function index</a></li><li><a href=\"{$safeVideosUrl}\">Training video directory</a></li></ul>"],
            ['tenant_admin_feature_deep_dive_1d', 'ArtsFolio setup deep dive', 86400, "Your ArtsFolio your admin is here:\n{$adminUrl}\n\nTour: {$tourUrl}\nFunction index: {$functionsUrl}\nTraining videos: {$videosUrl}\n", "<p>Your your admin is here: <a href=\"{$safeAdminUrl}\">{$safeAdminUrl}</a></p><p>Work through the <a href=\"{$safeTourUrl}\">setup tour</a>, the <a href=\"{$safeFunctionsUrl}\">tenant function index</a>, and the <a href=\"{$safeVideosUrl}\">training video directory</a>.</p>"],
            ['tenant_admin_weekly_checkin', 'ArtsFolio weekly check-in', 604800, "Weekly ArtsFolio check-in.\n\nAdmin: {$adminUrl}\nHelp: {$helpUrl}\nTraining videos: {$videosUrl}\n", "<p>Weekly ArtsFolio check-in.</p><ul><li><a href=\"{$safeAdminUrl}\">Open your admin</a></li><li><a href=\"{$safeHelpUrl}\">Open help</a></li><li><a href=\"{$safeVideosUrl}\">Open training video directory</a></li></ul>"],
        ];

        foreach ($schedule as [$templateKey, $subject, $delaySeconds, $bodyText, $bodyHtml]) {
            if ($this->lifecycleEmailExists($tenantId, $userId, $templateKey)) {
                continue;
            }

            $this->insertKnown('email_outbox', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'recipient_email' => $email,
                'recipient_name' => $name,
                'subject' => $subject,
                'body_text' => $bodyText,
                'body_html' => $bodyHtml,
                'template_key' => $templateKey,
                'status' => 'queued',
                'available_at' => gmdate('Y-m-d H:i:s', time() + $delaySeconds),
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ]);
        }
    }


    /**
     * Returns true when a lifecycle message already exists for this tenant,
     * user, and template in any delivery state.
     */
    private function lifecycleEmailExists(
        int $tenantId,
        int $userId,
        string $templateKey,
    ): bool {
        $stmt = $this->pdo->prepare(
            "SELECT 1
               FROM email_outbox
              WHERE tenant_id = :tenant_id
                AND user_id = :user_id
                AND template_key = :template_key
              LIMIT 1"
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'template_key' => $templateKey,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function seedTenantCss(int $tenantId): void
    {
        if (!$this->tableExists('tenant_settings')) {
            return;
        }

        $css = $this->defaultTenantCss();
        if ($css === '') {
            return;
        }

        $stmt = $this->pdo->prepare("SELECT id FROM tenant_settings WHERE tenant_id = :tenant_id AND setting_key = 'custom_css' LIMIT 1");
        $stmt->execute(['tenant_id' => $tenantId]);
        $existingId = $stmt->fetchColumn();

        if ($existingId !== false) {
            $this->updateKnown('tenant_settings', (int) $existingId, ['setting_value' => $css, 'updated_at' => $this->now()]);
            return;
        }

        $this->insertKnown('tenant_settings', [
            'tenant_id' => $tenantId,
            'setting_key' => 'custom_css',
            'setting_value' => $css,
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ]);
    }

    private function defaultTenantCss(): string
    {
        $root = dirname(__DIR__, 3);
        $parts = [];
        foreach (['public/assets/site.css', 'public/assets/platform.css'] as $relativePath) {
            $path = $root . '/' . $relativePath;
            if (is_file($path)) {
                $parts[] = "/* Seeded from {$relativePath}. */\n" . trim((string) file_get_contents($path));
            }
        }

        return trim(implode("\n\n", $parts));
    }

    /**
     * Finds a tenant-scoped role by canonical slug first, then legacy names.
     */
    private function findTenantRoleId(array $roleSlugs): ?int
    {
        $roleSlugs = array_values(array_unique(array_filter(array_map(
            static fn (string $role): string => strtolower(trim($role)),
            $roleSlugs,
        ))));

        if ($roleSlugs === []) {
            return null;
        }

        $placeholders = implode(', ', array_fill(0, count($roleSlugs), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT id
             FROM roles
             WHERE scope = 'tenant'
               AND (slug IN ({$placeholders}) OR LOWER(name) IN ({$placeholders}))
             ORDER BY CASE slug WHEN 'owner' THEN 0 WHEN 'tenant_owner' THEN 1 WHEN 'admin' THEN 2 ELSE 3 END, id
             LIMIT 1"
        );
        $stmt->execute(array_merge($roleSlugs, $roleSlugs));
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function insertKnown(string $table, array $values): void
    {
        $columns = $this->tableColumns($table);
        $filtered = [];

        foreach ($values as $key => $value) {
            if (isset($columns[$key])) {
                $filtered[$key] = $value;
            }
        }

        if ($filtered === []) {
            throw new RuntimeException("No known columns for {$table}");
        }

        $columnSql = implode(', ', array_map(static fn (string $column): string => "`{$column}`", array_keys($filtered)));
        $paramSql = implode(', ', array_map(static fn (string $column): string => ":{$column}", array_keys($filtered)));

        $stmt = $this->pdo->prepare("INSERT INTO `{$table}` ({$columnSql}) VALUES ({$paramSql})");
        $stmt->execute($filtered);
    }

    private function updateKnown(string $table, int $id, array $values): void
    {
        $columns = $this->tableColumns($table);
        $filtered = [];

        foreach ($values as $key => $value) {
            if (isset($columns[$key])) {
                $filtered[$key] = $value;
            }
        }

        if ($filtered === []) {
            return;
        }

        $setSql = implode(', ', array_map(static fn (string $column): string => "`{$column}` = :{$column}", array_keys($filtered)));
        $filtered['id'] = $id;

        $stmt = $this->pdo->prepare("UPDATE `{$table}` SET {$setSql} WHERE id = :id");
        $stmt->execute($filtered);
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $stmt->execute(['table' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function tableColumns(string $table): array
    {
        $stmt = $this->pdo->query("SHOW COLUMNS FROM `{$table}`");
        $columns = [];

        foreach ($stmt->fetchAll() as $row) {
            $columns[(string) $row['Field']] = true;
        }

        return $columns;
    }

    private function uuid(): string
    {
        return (string) $this->pdo->query('SELECT UUID()')->fetchColumn();
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}

// End of file.
