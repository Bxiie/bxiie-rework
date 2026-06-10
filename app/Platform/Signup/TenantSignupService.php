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

        $this->pdo->beginTransaction();

        try {
            $this->ensureTenantSlugAvailable($slug);
            $this->ensureDomainAvailable($domain);

            $tenantId = $this->createTenant($slug, $siteName);
            $userId = $this->createOrUpdateUser($adminEmail, $adminName);
            $this->createOrUpdatePasswordIdentity($userId, $adminEmail, $passwordHash);
            $this->createTenantDomain($tenantId, $domain, true);
            $this->createTenantMembership($tenantId, $userId);
            $this->seedTenantCss($tenantId);
            $this->assignTenantAdminRole($tenantId, $userId);
            $this->queueProvisioningJobs($tenantId, $domain);
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
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
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

    private function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/', $slug)) {
            throw new RuntimeException('Tenant slug must be 3-63 lowercase letters, numbers, or hyphens.');
        }

        return $slug;
    }

    private function ensureTenantSlugAvailable(string $slug): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM tenants WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);

        if ($stmt->fetchColumn() !== false) {
            throw new RuntimeException('Tenant slug is already in use.');
        }
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

        $this->insertKnown('tenant_memberships', [
            'uuid' => $this->uuid(),
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'status' => 'active',
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ]);
    }

    private function assignTenantAdminRole(int $tenantId, int $userId): void
    {
        if (!$this->tableExists('roles') || !$this->tableExists('role_assignments')) {
            return;
        }

        $roleId = $this->findRoleId(['tenant_owner', 'tenant_admin', 'owner', 'admin']);

        if ($roleId === null) {
            return;
        }

        $this->insertKnown('role_assignments', [
            'uuid' => $this->uuid(),
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'role_id' => $roleId,
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ]);
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

    private function queueLifecycleEmail(int $tenantId, int $userId, string $email, string $name, string $slug): void
    {
        if (!$this->tableExists('email_outbox')) {
            return;
        }

        $schedule = [
            ['tenant_admin_welcome_6h', 'Welcome to ArtsFolio', 21600],
            ['tenant_admin_feature_deep_dive_1d', 'ArtsFolio setup deep dive', 86400],
            ['tenant_admin_weekly_checkin', 'ArtsFolio weekly check-in', 604800],
        ];

        foreach ($schedule as [$templateKey, $subject, $delaySeconds]) {
            $this->insertKnown('email_outbox', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'recipient_email' => $email,
                'recipient_name' => $name,
                'subject' => $subject,
                'body_text' => "ArtsFolio {$templateKey} for tenant {$slug}.",
                'body_html' => "<p>ArtsFolio {$templateKey} for tenant <strong>" . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . "</strong>.</p>",
                'template_key' => $templateKey,
                'status' => 'queued',
                'available_at' => date('Y-m-d H:i:s', time() + $delaySeconds),
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ]);
        }
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

    private function findRoleId(array $roleNames): ?int
    {
        $placeholders = implode(', ', array_fill(0, count($roleNames), '?'));
        $stmt = $this->pdo->prepare("SELECT id FROM roles WHERE name IN ({$placeholders}) ORDER BY id LIMIT 1");
        $stmt->execute($roleNames);
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
        return date('Y-m-d H:i:s');
    }
}

// End of file.
