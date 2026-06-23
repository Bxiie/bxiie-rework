<?php

/**
 * Scale-test tenant fixture service.
 */

declare(strict_types=1);

namespace App\Platform\ScaleTesting;

use PDO;
use RuntimeException;

/**
 * Creates and removes synthetic tenants that are clearly marked as scale fixtures.
 *
 * Cleanup is intentionally conservative: tenants must have both the scale slug
 * prefix and the tenant_settings marker before any tenant-scoped data is removed.
 */
final class ScaleTenantFixtureService
{
    public const MARKER_KEY = 'scale_dataset_marker';
    public const MARKER_VALUE = 'artsfolio-scale-fixture-v1';
    public const SLUG_PREFIX = 'scale-';
    public const USER_EMAIL_DOMAIN = 'scale-fixtures.artsfol.io';
    public const FIXTURE_PASSWORD = 'ScaleTenantFixture!2026';

    /**
     * Plan mix used for synthetic tenants. Admin-user counts intentionally match
     * each plan's allowed_admin_users value when that column exists.
     */
    private const PLAN_SEQUENCE = ['free', 'studio', 'pro', 'collective'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $root,
    ) {
    }

    /**
     * Seeds isolated scale tenants and returns a compact operation summary.
     *
     * @return array<string,int|string>
     */
    public function seed(int $tenantCount, int $artworksPerTenant, int $eventsPerTenant): array
    {
        $tenantCount = max(0, $tenantCount);
        $artworksPerTenant = max(0, min(500, $artworksPerTenant));
        $eventsPerTenant = max(0, min(5000, $eventsPerTenant));

        $this->ensureFixtureImage();
        $fixtureSource = $this->root . '/storage/uploads/scale-fixtures/placeholder.png';
        $portfolioTypeId = $this->ensureArtworkType('portfolio_images', 'Portfolio Images');
        $plans = $this->loadFixturePlans();
        $createdOrUpdated = 0;

        for ($i = 1; $i <= $tenantCount; $i++) {
            $slug = self::SLUG_PREFIX . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $tenantName = 'Scale Fixture Tenant ' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $plan = $plans[($i - 1) % count($plans)];
            $tenantId = $this->upsertTenant($slug, $tenantName);

            $this->upsertTenantDomain($tenantId, $slug . '.artsfol.io');
            $this->upsertTenantPlanAssignment($tenantId, (int) $plan['id'], (string) $plan['slug']);
            $this->upsertTenantSetting($tenantId, self::MARKER_KEY, self::MARKER_VALUE);
            $this->upsertTenantSetting($tenantId, 'site_title', $tenantName);
            $this->upsertTenantSetting($tenantId, 'billing_plan', (string) $plan['slug']);
            $this->upsertTenantSetting($tenantId, 'directory_opt_in', '0');
            $this->upsertTenantSetting($tenantId, 'scale_dataset_note', 'Synthetic tenant created by platform scale fixture tooling.');

            $this->ensureTenantUsers($tenantId, $slug, $tenantName, (string) $plan['slug'], (int) $plan['admin_users']);

            $sectionId = $this->upsertPortfolioSection($tenantId, 'Scale Section', 'scale-section');

            for ($j = 1; $j <= $artworksPerTenant; $j++) {
                $mediaId = $this->upsertScaleMedia($fixtureSource, $tenantId, $slug, $j);
                $artworkId = $this->upsertScaleArtwork($tenantId, $mediaId, $j);
                $this->assignArtworkType($artworkId, $portfolioTypeId);
                $this->assignArtworkSection($artworkId, $sectionId, $j);
                $this->assignHomepageArtwork($tenantId, $artworkId, $j);
            }

            if ($eventsPerTenant > 0) {
                $this->seedAnalyticsEvents($tenantId, $eventsPerTenant);
            }

            $createdOrUpdated++;
        }

        $summary = $this->summary();
        $summary['seeded_or_updated'] = $createdOrUpdated;

        return $summary;
    }

    /**
     * Removes only tenants that match both the scale slug prefix and marker setting.
     *
     * @return array<string,int|string>
     */
    public function cleanup(): array
    {
        $this->pdo->exec('DROP TEMPORARY TABLE IF EXISTS scale_fixture_tenant_ids');
        $this->pdo->exec('CREATE TEMPORARY TABLE scale_fixture_tenant_ids (tenant_id BIGINT UNSIGNED PRIMARY KEY)');

        $stmt = $this->pdo->prepare(
            'INSERT INTO scale_fixture_tenant_ids (tenant_id)
             SELECT t.id
               FROM tenants t
               JOIN tenant_settings s ON s.tenant_id = t.id
              WHERE t.slug LIKE :slug_prefix
                AND s.setting_key = :marker_key
                AND s.setting_value = :marker_value'
        );
        $stmt->execute([
            'slug_prefix' => self::SLUG_PREFIX . '%',
            'marker_key' => self::MARKER_KEY,
            'marker_value' => self::MARKER_VALUE,
        ]);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM scale_fixture_tenant_ids')->fetchColumn();
        if ($count === 0) {
            return ['removed' => 0] + $this->summary();
        }

        $this->prepareScaleUserIds();

        $this->deleteIfExists('email_outbox', 'tenant_id');
        $this->deleteUserRowsIfExists('email_outbox', 'user_id');
        $this->deleteIfExists('tenant_session_bridge_tickets', 'tenant_id');
        $this->deleteUserRowsIfExists('tenant_session_bridge_tickets', 'user_id');
        $this->deleteUserRowsIfExists('oauth_refresh_tokens', 'user_id');
        $this->deleteUserRowsIfExists('user_sessions', 'user_id');
        $this->deleteUserRowsIfExists('oauth_access_tokens', 'user_id');
        $this->deleteUserRowsIfExists('password_reset_tokens', 'user_id');
        $this->deleteUserRowsIfExists('email_verification_tokens', 'user_id');
        $this->deleteUserRowsIfExists('oauth_accounts', 'user_id');
        $this->deleteUserRowsIfExists('user_identities', 'user_id');
        $this->deleteUserRowsIfExists('platform_roles', 'user_id');
        $this->deleteUserRowsIfExists('audit_log', 'user_id');

        $this->deleteIfExists('homepage_artwork_assignments', 'tenant_id');
        $this->deleteJoinedArtworkChild('artwork_type_assignments', 'artwork_id');
        $this->deleteJoinedArtworkChild('artwork_section_assignments', 'artwork_id');
        $this->deleteJoinedMediaChild('media_asset_variants', 'media_asset_id');
        $this->deleteIfExists('analytics_events', 'tenant_id');
        $this->deleteIfExists('analytics_rollups_daily', 'tenant_id');
        $this->deleteIfExists('email_signups', 'tenant_id');
        $this->deleteIfExists('newsletter_subscribers', 'tenant_id');
        $this->deleteIfExists('contact_messages', 'tenant_id');
        $this->deleteIfExists('pages', 'tenant_id');
        $this->deleteIfExists('exhibitions', 'tenant_id');
        $this->deleteIfExists('sales_cart_items', 'tenant_id');
        $this->deleteIfExists('sales_carts', 'tenant_id');
        $this->deleteIfExists('sales_order_items', 'tenant_id');
        $this->deleteIfExists('sales_orders', 'tenant_id');
        $this->deleteIfExists('role_assignments', 'tenant_id');
        $this->deleteUserRowsIfExists('role_assignments', 'user_id');
        $this->deleteIfExists('tenant_plan_assignments', 'tenant_id');
        $this->deleteIfExists('tenant_memberships', 'tenant_id');
        $this->deleteIfExists('tenant_users', 'tenant_id');
        $this->deleteIfExists('background_jobs', 'tenant_id');
        $this->deleteIfExists('oauth_clients', 'tenant_id');
        $this->deleteIfExists('tenant_domain_dns_results', 'tenant_id');
        $this->deleteIfExists('domain_artifacts', 'tenant_id');
        $this->deleteIfExists('tenant_domains', 'tenant_id');
        $this->deleteIfExists('tenant_settings', 'tenant_id');
        $this->deleteIfExists('artworks', 'tenant_id');
        $this->deleteIfExists('media_assets', 'tenant_id');
        $this->deleteIfExists('portfolio_sections', 'tenant_id');
        $this->deleteIfExists('audit_log', 'tenant_id');
        $this->deleteScaleUsers();
        $this->deleteIfExists('tenants', 'id');
        $this->removeScaleUploadDirectories();

        return ['removed' => $count] + $this->summary();
    }

    /**
     * Returns counts for current synthetic scale data.
     *
     * @return array<string,int|string>
     */
    public function summary(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
               FROM tenants t
               JOIN tenant_settings s ON s.tenant_id = t.id
              WHERE t.slug LIKE :slug_prefix
                AND s.setting_key = :marker_key
                AND s.setting_value = :marker_value'
        );
        $stmt->execute([
            'slug_prefix' => self::SLUG_PREFIX . '%',
            'marker_key' => self::MARKER_KEY,
            'marker_value' => self::MARKER_VALUE,
        ]);
        $tenantCount = (int) $stmt->fetchColumn();

        return [
            'marker_key' => self::MARKER_KEY,
            'marker_value' => self::MARKER_VALUE,
            'slug_prefix' => self::SLUG_PREFIX,
            'user_email_domain' => self::USER_EMAIL_DOMAIN,
            'tenants' => $tenantCount,
            'users' => $this->countScaleUsers(),
            'plan_distribution' => $this->planDistributionSummary(),
            'artworks' => $this->countMarkedTenantRows('artworks'),
            'media_assets' => $this->countMarkedTenantRows('media_assets'),
            'analytics_events' => $this->countMarkedTenantRows('analytics_events'),
        ];
    }

    private function ensureFixtureImage(): void
    {
        $dir = $this->root . '/storage/uploads/scale-fixtures';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create scale fixture upload directory.');
        }

        $png = $dir . '/placeholder.png';
        if (!is_file($png)) {
            file_put_contents($png, base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAIAAAAlC+aJAAAALklEQVR4nO3BAQ0AAADCoPdPbQ43oAAAAAAAAAAAAAAAAAAAAAAAAAAAADgZQABAAE24JgAAAAASUVORK5CYII=',
                true
            ));
        }
    }

    private function upsertTenant(string $slug, string $name): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO tenants (uuid, slug, name, status, created_at, updated_at)
             VALUES (UUID(), :slug, :name, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                status = 'active',
                updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute(['slug' => $slug, 'name' => $name]);

        $select = $this->pdo->prepare('SELECT id FROM tenants WHERE slug = :slug LIMIT 1');
        $select->execute(['slug' => $slug]);

        return (int) $select->fetchColumn();
    }

    private function upsertTenantDomain(int $tenantId, string $hostname): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO tenant_domains (tenant_id, hostname, domain_type, status, is_primary, created_at, updated_at)
             VALUES (:tenant_id, :hostname, 'platform_subdomain', 'active', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE
                tenant_id = VALUES(tenant_id),
                status = 'active',
                is_primary = 1,
                updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'hostname' => $hostname]);
    }

    private function upsertTenantSetting(int $tenantId, string $key, string $value): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, created_at, updated_at)
             VALUES (:tenant_id, :setting_key, :setting_value, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'setting_key' => $key, 'setting_value' => $value]);
    }

    /**
     * Loads active fixture plans with a fallback for sparse development databases.
     *
     * @return array<int,array{id:int,slug:string,admin_users:int}>
     */
    private function loadFixturePlans(): array
    {
        $plans = [];
        foreach (self::PLAN_SEQUENCE as $slug) {
            $plan = $this->findPlan($slug);
            if ($plan !== null) {
                $plans[] = $plan;
            }
        }

        if ($plans === []) {
            throw new RuntimeException('No pricing plans found. Run database migrations before seeding scale tenants.');
        }

        return $plans;
    }

    /**
     * @return array{id:int,slug:string,admin_users:int}|null
     */
    private function findPlan(string $slug): ?array
    {
        if (!$this->tableExists('plans')) {
            return null;
        }

        $columns = 'id, slug';
        $hasAdminLimit = $this->columnExists('plans', 'allowed_admin_users');
        if ($hasAdminLimit) {
            $columns .= ', allowed_admin_users';
        }

        $stmt = $this->pdo->prepare("SELECT {$columns} FROM plans WHERE slug = :slug LIMIT 1");
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $fallback = match ($slug) {
            'free' => 1,
            'studio' => 3,
            'pro' => 10,
            'collective' => 25,
            default => 1,
        };

        return [
            'id' => (int) $row['id'],
            'slug' => (string) $row['slug'],
            'admin_users' => max(1, (int) ($hasAdminLimit ? ($row['allowed_admin_users'] ?? $fallback) : $fallback)),
        ];
    }

    private function upsertTenantPlanAssignment(int $tenantId, int $planId, string $planSlug): void
    {
        if (!$this->tableExists('tenant_plan_assignments')) {
            $this->upsertTenantSetting($tenantId, 'billing_plan', $planSlug);
            return;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO tenant_plan_assignments (tenant_id, plan_id, status, created_at)
             VALUES (:tenant_id, :plan_id, 'manual', CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE
                plan_id = VALUES(plan_id),
                status = 'manual'"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'plan_id' => $planId]);
    }

    private function ensureTenantUsers(int $tenantId, string $tenantSlug, string $tenantName, string $planSlug, int $adminUserCount): void
    {
        $adminUserCount = max(1, $adminUserCount);
        $ownerRoleId = $this->tenantRoleId('owner');
        $adminRoleId = $this->tenantRoleId('admin');

        for ($i = 1; $i <= $adminUserCount; $i++) {
            $role = $i === 1 ? 'owner' : 'admin';
            $email = $tenantSlug . '-admin-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT) . '@' . self::USER_EMAIL_DOMAIN;
            $displayName = $tenantName . ' ' . ucfirst($role) . ' ' . $i . ' [' . $planSlug . ']';
            $userId = $this->upsertScaleUser($email, $displayName);
            $this->upsertTenantUser($tenantId, $userId, $role);
            $this->upsertTenantMembership($tenantId, $userId);
            $this->assignTenantRole($tenantId, $userId, $role === 'owner' ? $ownerRoleId : $adminRoleId);
        }
    }

    private function upsertScaleUser(string $email, string $displayName): int
    {
        $columns = ['uuid', 'email', 'password_hash', 'display_name', 'email_verified_at', 'created_at', 'updated_at'];
        $values = ['UUID()', ':email', ':password_hash', ':display_name', 'CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP'];
        $updates = ['password_hash = VALUES(password_hash)', 'display_name = VALUES(display_name)', 'email_verified_at = COALESCE(email_verified_at, CURRENT_TIMESTAMP)', 'updated_at = CURRENT_TIMESTAMP'];
        $params = [
            'email' => $email,
            'password_hash' => password_hash(self::FIXTURE_PASSWORD, PASSWORD_DEFAULT),
            'display_name' => $displayName,
        ];

        if ($this->columnExists('users', 'status')) {
            $columns[] = 'status';
            $values[] = "'active'";
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')
             ON DUPLICATE KEY UPDATE ' . implode(', ', $updates)
        );
        $stmt->execute($params);

        $select = $this->pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $select->execute(['email' => $email]);
        $userId = (int) $select->fetchColumn();
        $this->upsertScaleUserIdentity($userId, $email, $displayName);

        return $userId;
    }

    private function upsertScaleUserIdentity(int $userId, string $email, string $displayName): void
    {
        if (!$this->tableExists('user_identities')) {
            return;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO user_identities (user_id, identity_type, provider, provider_subject, email, display_name, metadata, verified_at, last_used_at, created_at, updated_at)
             VALUES (:user_id, 'local_password', 'scale_fixture', :provider_subject, :email, :display_name, JSON_OBJECT('scale_fixture_marker', :marker), CURRENT_TIMESTAMP, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                email = VALUES(email),
                display_name = VALUES(display_name),
                metadata = VALUES(metadata),
                verified_at = COALESCE(verified_at, CURRENT_TIMESTAMP),
                updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([
            'user_id' => $userId,
            'provider_subject' => $email,
            'email' => $email,
            'display_name' => $displayName,
            'marker' => self::MARKER_VALUE,
        ]);
    }

    private function upsertTenantUser(int $tenantId, int $userId, string $role): void
    {
        if (!$this->tableExists('tenant_users')) {
            return;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO tenant_users (tenant_id, user_id, role, status, created_at)
             VALUES (:tenant_id, :user_id, :role, 'active', CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE
                role = VALUES(role),
                status = 'active'"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'user_id' => $userId, 'role' => $role]);
    }

    private function upsertTenantMembership(int $tenantId, int $userId): void
    {
        if (!$this->tableExists('tenant_memberships')) {
            return;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO tenant_memberships (tenant_id, user_id, status, created_at, updated_at)
             VALUES (:tenant_id, :user_id, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE
                status = 'active',
                updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'user_id' => $userId]);
    }

    private function tenantRoleId(string $slug): ?int
    {
        if (!$this->tableExists('roles')) {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT id FROM roles WHERE scope = 'tenant' AND slug = :slug LIMIT 1");
        $stmt->execute(['slug' => $slug]);
        $id = (int) ($stmt->fetchColumn() ?: 0);

        return $id > 0 ? $id : null;
    }

    private function assignTenantRole(int $tenantId, int $userId, ?int $roleId): void
    {
        if ($roleId === null || !$this->tableExists('role_assignments')) {
            return;
        }

        $stmt = $this->pdo->prepare('INSERT IGNORE INTO role_assignments (role_id, user_id, tenant_id, created_at) VALUES (:role_id, :user_id, :tenant_id, CURRENT_TIMESTAMP)');
        $stmt->execute(['role_id' => $roleId, 'user_id' => $userId, 'tenant_id' => $tenantId]);
    }

    private function ensureArtworkType(string $code, string $name): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO artwork_types (code, name, description)
             VALUES (:code, :name, 'Scale fixture compatible artwork type.')
             ON DUPLICATE KEY UPDATE name = VALUES(name)"
        );
        $stmt->execute(['code' => $code, 'name' => $name]);

        $select = $this->pdo->prepare('SELECT id FROM artwork_types WHERE code = :code LIMIT 1');
        $select->execute(['code' => $code]);

        return (int) $select->fetchColumn();
    }

    private function upsertPortfolioSection(int $tenantId, string $name, string $slug): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO portfolio_sections (uuid, tenant_id, name, slug, show_as_tab, sort_order, status, created_at, updated_at)
             VALUES (UUID(), :tenant_id, :name, :slug, 1, 10, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                show_as_tab = 1,
                status = 'active',
                updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'name' => $name, 'slug' => $slug]);

        $select = $this->pdo->prepare('SELECT id FROM portfolio_sections WHERE tenant_id = :tenant_id AND slug = :slug LIMIT 1');
        $select->execute(['tenant_id' => $tenantId, 'slug' => $slug]);

        return (int) $select->fetchColumn();
    }

    private function upsertScaleMedia(string $fixtureSource, int $tenantId, string $tenantSlug, int $index): int
    {
        $uuidKey = $tenantSlug . '-artwork-' . str_pad((string) $index, 5, '0', STR_PAD_LEFT);
        $relativeDir = 'storage/uploads/artwork/' . $tenantSlug;
        $absoluteDir = $this->root . '/' . $relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('Could not create tenant scale upload directory.');
        }

        $filename = $uuidKey . '.png';
        $relativePath = $relativeDir . '/' . $filename;
        $absolutePath = $this->root . '/' . $relativePath;
        if (!is_file($absolutePath)) {
            copy($fixtureSource, $absolutePath);
        }

        $select = $this->pdo->prepare('SELECT id FROM media_assets WHERE tenant_id = :tenant_id AND storage_path = :storage_path LIMIT 1');
        $select->execute(['tenant_id' => $tenantId, 'storage_path' => $relativePath]);
        $mediaId = (int) ($select->fetchColumn() ?: 0);
        $bytes = filesize($absolutePath) ?: null;

        if ($mediaId > 0) {
            $stmt = $this->pdo->prepare(
                "UPDATE media_assets
                    SET original_filename = :original_filename,
                        mime_type = 'image/png',
                        file_size_bytes = :file_size_bytes,
                        width = 64,
                        height = 64,
                        alt_text = :alt_text,
                        title = :title,
                        is_private = 0,
                        updated_at = CURRENT_TIMESTAMP
                  WHERE id = :media_id"
            );
            $stmt->execute([
                'media_id' => $mediaId,
                'original_filename' => $filename,
                'file_size_bytes' => $bytes,
                'alt_text' => 'Scale fixture artwork ' . $index,
                'title' => 'Scale Fixture Artwork ' . $index,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                "INSERT INTO media_assets (
                    uuid,
                    tenant_id,
                    original_filename,
                    storage_path,
                    mime_type,
                    file_size_bytes,
                    width,
                    height,
                    alt_text,
                    title,
                    is_private,
                    created_at,
                    updated_at
                ) VALUES (
                    UUID(),
                    :tenant_id,
                    :original_filename,
                    :storage_path,
                    'image/png',
                    :file_size_bytes,
                    64,
                    64,
                    :alt_text,
                    :title,
                    0,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )"
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'original_filename' => $filename,
                'storage_path' => $relativePath,
                'file_size_bytes' => $bytes,
                'alt_text' => 'Scale fixture artwork ' . $index,
                'title' => 'Scale Fixture Artwork ' . $index,
            ]);
            $mediaId = (int) $this->pdo->lastInsertId();
        }

        if ($this->tableExists('media_asset_variants')) {
            $this->upsertMediaVariant($mediaId, 'original', $relativePath, 'image/png', 64, 64, $bytes);
            $this->upsertMediaVariant($mediaId, 'thumb', $relativePath, 'image/png', 64, 64, $bytes);
            $this->upsertMediaVariant($mediaId, 'medium', $relativePath, 'image/png', 64, 64, $bytes);
            $this->upsertMediaVariant($mediaId, 'large', $relativePath, 'image/png', 64, 64, $bytes);
        }

        return $mediaId;
    }

    private function upsertMediaVariant(int $mediaId, string $variant, string $path, string $mime, int $width, int $height, ?int $bytes): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO media_asset_variants (media_asset_id, variant_key, storage_path, mime_type, width, height, file_size_bytes, created_at, updated_at)
             VALUES (:media_asset_id, :variant_key, :storage_path, :mime_type, :width, :height, :file_size_bytes, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE
                storage_path = VALUES(storage_path),
                mime_type = VALUES(mime_type),
                width = VALUES(width),
                height = VALUES(height),
                file_size_bytes = VALUES(file_size_bytes),
                updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([
            'media_asset_id' => $mediaId,
            'variant_key' => $variant,
            'storage_path' => $path,
            'mime_type' => $mime,
            'width' => $width,
            'height' => $height,
            'file_size_bytes' => $bytes,
        ]);
    }

    private function upsertScaleArtwork(int $tenantId, int $mediaId, int $index): int
    {
        $slug = 'scale-artwork-' . str_pad((string) $index, 5, '0', STR_PAD_LEFT);
        $columns = [
            'uuid', 'tenant_id', 'primary_media_id', 'title', 'slug', 'description', 'medium', 'year_created', 'status', 'sort_order', 'created_at', 'updated_at',
        ];
        $values = [
            'UUID()', ':tenant_id', ':primary_media_id', ':title', ':slug', ':description', ':medium', ':year_created', "'published'", ':sort_order', 'CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP',
        ];
        $params = [
            'tenant_id' => $tenantId,
            'primary_media_id' => $mediaId,
            'title' => 'Scale Fixture Artwork ' . $index,
            'slug' => $slug,
            'description' => 'Synthetic scale-test artwork. Safe to remove with scale fixture cleanup.',
            'medium' => 'Fixture pixels',
            'year_created' => (string) (2000 + ($index % 25)),
            'sort_order' => $index,
        ];

        if ($this->columnExists('artworks', 'sale_status')) {
            $columns[] = 'sale_status';
            $values[] = "'nfs'";
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO artworks (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')
             ON DUPLICATE KEY UPDATE
                primary_media_id = VALUES(primary_media_id),
                title = VALUES(title),
                status = VALUES(status),
                sort_order = VALUES(sort_order),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute($params);

        $select = $this->pdo->prepare('SELECT id FROM artworks WHERE tenant_id = :tenant_id AND slug = :slug LIMIT 1');
        $select->execute(['tenant_id' => $tenantId, 'slug' => $slug]);

        return (int) $select->fetchColumn();
    }

    private function assignArtworkType(int $artworkId, int $typeId): void
    {
        $stmt = $this->pdo->prepare('INSERT IGNORE INTO artwork_type_assignments (artwork_id, type_id) VALUES (:artwork_id, :type_id)');
        $stmt->execute(['artwork_id' => $artworkId, 'type_id' => $typeId]);
    }

    private function assignArtworkSection(int $artworkId, int $sectionId, int $sortOrder): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO artwork_section_assignments (artwork_id, section_id, sort_order, created_at)
             VALUES (:artwork_id, :section_id, :sort_order, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order)"
        );
        $stmt->execute(['artwork_id' => $artworkId, 'section_id' => $sectionId, 'sort_order' => $sortOrder]);
    }

    private function assignHomepageArtwork(int $tenantId, int $artworkId, int $sortOrder): void
    {
        if (!$this->tableExists('homepage_artwork_assignments')) {
            return;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO homepage_artwork_assignments (tenant_id, artwork_id, sort_order, created_at, updated_at)
             VALUES (:tenant_id, :artwork_id, :sort_order, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'artwork_id' => $artworkId, 'sort_order' => $sortOrder]);
    }

    private function seedAnalyticsEvents(int $tenantId, int $eventCount): void
    {
        if (!$this->tableExists('analytics_events')) {
            return;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO analytics_events (tenant_id, event_type, path, referrer, ip_hash, user_agent, country, region, city, created_at)
             VALUES (:tenant_id, :event_type, :path, NULL, SHA2(CONCAT(:tenant_id_hash, '-', :counter), 256), 'ArtsFolio scale fixture', 'US', 'VT', 'Scaleville', DATE_SUB(CURRENT_TIMESTAMP, INTERVAL :minutes MINUTE))"
        );

        for ($i = 1; $i <= $eventCount; $i++) {
            $stmt->execute([
                'tenant_id' => $tenantId,
                'tenant_id_hash' => (string) $tenantId,
                'counter' => (string) $i,
                'event_type' => $i % 5 === 0 ? 'artwork_view' : 'page_view',
                'path' => $i % 5 === 0 ? '/artwork/scale-artwork-' . str_pad((string) (($i % 50) + 1), 5, '0', STR_PAD_LEFT) : '/',
                'minutes' => $i,
            ]);
        }
    }

    private function countMarkedTenantRows(string $table): int
    {
        if (!$this->tableExists($table) || !$this->columnExists($table, 'tenant_id')) {
            return 0;
        }

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
               FROM `{$table}` x
               JOIN tenants t ON t.id = x.tenant_id
               JOIN tenant_settings s ON s.tenant_id = t.id
              WHERE t.slug LIKE :slug_prefix
                AND s.setting_key = :marker_key
                AND s.setting_value = :marker_value"
        );
        $stmt->execute([
            'slug_prefix' => self::SLUG_PREFIX . '%',
            'marker_key' => self::MARKER_KEY,
            'marker_value' => self::MARKER_VALUE,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function countScaleUsers(): int
    {
        if (!$this->tableExists('users')) {
            return 0;
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email LIKE :email_pattern');
        $stmt->execute(['email_pattern' => '%@' . self::USER_EMAIL_DOMAIN]);

        return (int) $stmt->fetchColumn();
    }

    private function planDistributionSummary(): string
    {
        if (!$this->tableExists('tenant_plan_assignments') || !$this->tableExists('plans')) {
            return 'unavailable';
        }

        try {
            $this->pdo->exec('DROP TEMPORARY TABLE IF EXISTS scale_fixture_summary_tenant_ids');
            $this->pdo->exec('CREATE TEMPORARY TABLE scale_fixture_summary_tenant_ids (tenant_id BIGINT UNSIGNED PRIMARY KEY)');
            $insert = $this->pdo->prepare(
                'INSERT INTO scale_fixture_summary_tenant_ids (tenant_id)
                 SELECT t.id
                   FROM tenants t
                   JOIN tenant_settings s ON s.tenant_id = t.id
                  WHERE t.slug LIKE :slug_prefix
                    AND s.setting_key = :marker_key
                    AND s.setting_value = :marker_value'
            );
            $insert->execute([
                'slug_prefix' => self::SLUG_PREFIX . '%',
                'marker_key' => self::MARKER_KEY,
                'marker_value' => self::MARKER_VALUE,
            ]);
            $orderSql = $this->columnExists('plans', 'display_order') ? 'MIN(p.display_order), p.slug' : 'p.slug';
            $stmt = $this->pdo->query(
                'SELECT p.slug, COUNT(*) AS tenant_count
                   FROM tenant_plan_assignments tpa
                   JOIN plans p ON p.id = tpa.plan_id
                   JOIN scale_fixture_summary_tenant_ids s ON s.tenant_id = tpa.tenant_id
                  GROUP BY p.slug
                  ORDER BY ' . $orderSql
            );
            $parts = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $parts[] = $row['slug'] . ':' . (int) $row['tenant_count'];
            }

            return $parts === [] ? 'none' : implode(', ', $parts);
        } catch (\Throwable) {
            return 'unavailable';
        }
    }

    private function prepareScaleUserIds(): void
    {
        $this->pdo->exec('DROP TEMPORARY TABLE IF EXISTS scale_fixture_user_ids');
        $this->pdo->exec('CREATE TEMPORARY TABLE scale_fixture_user_ids (user_id BIGINT UNSIGNED PRIMARY KEY)');

        if (!$this->tableExists('users')) {
            return;
        }

        if ($this->tableExists('tenant_users')) {
            $this->pdo->exec(
                "INSERT IGNORE INTO scale_fixture_user_ids (user_id)
                 SELECT DISTINCT u.id
                   FROM users u
                   JOIN tenant_users tu ON tu.user_id = u.id
                   JOIN scale_fixture_tenant_ids s ON s.tenant_id = tu.tenant_id
                  WHERE u.email LIKE '%@" . self::USER_EMAIL_DOMAIN . "'"
            );
        }

        if ($this->tableExists('tenant_memberships')) {
            $this->pdo->exec(
                "INSERT IGNORE INTO scale_fixture_user_ids (user_id)
                 SELECT DISTINCT u.id
                   FROM users u
                   JOIN tenant_memberships tm ON tm.user_id = u.id
                   JOIN scale_fixture_tenant_ids s ON s.tenant_id = tm.tenant_id
                  WHERE u.email LIKE '%@" . self::USER_EMAIL_DOMAIN . "'"
            );
        }
    }

    private function deleteIfExists(string $table, string $column): void
    {
        if (!$this->tableExists($table) || !$this->columnExists($table, $column)) {
            return;
        }

        $this->pdo->exec("DELETE t FROM `{$table}` t JOIN scale_fixture_tenant_ids s ON s.tenant_id = t.`{$column}`");
    }

    private function deleteUserRowsIfExists(string $table, string $column): void
    {
        if (!$this->tableExists($table) || !$this->columnExists($table, $column)) {
            return;
        }

        $this->pdo->exec("DELETE t FROM `{$table}` t JOIN scale_fixture_user_ids s ON s.user_id = t.`{$column}`");
    }

    private function deleteScaleUsers(): void
    {
        if (!$this->tableExists('users')) {
            return;
        }

        $this->pdo->exec('DELETE u FROM users u JOIN scale_fixture_user_ids s ON s.user_id = u.id');
    }

    private function deleteJoinedArtworkChild(string $table, string $artworkIdColumn): void
    {
        if (!$this->tableExists($table)) {
            return;
        }

        $this->pdo->exec(
            "DELETE c
               FROM `{$table}` c
               JOIN artworks a ON a.id = c.`{$artworkIdColumn}`
               JOIN scale_fixture_tenant_ids s ON s.tenant_id = a.tenant_id"
        );
    }

    private function deleteJoinedMediaChild(string $table, string $mediaIdColumn): void
    {
        if (!$this->tableExists($table)) {
            return;
        }

        $this->pdo->exec(
            "DELETE c
               FROM `{$table}` c
               JOIN media_assets m ON m.id = c.`{$mediaIdColumn}`
               JOIN scale_fixture_tenant_ids s ON s.tenant_id = m.tenant_id"
        );
    }

    private function removeScaleUploadDirectories(): void
    {
        $base = $this->root . '/storage/uploads/artwork';
        if (!is_dir($base)) {
            return;
        }

        foreach (glob($base . '/' . self::SLUG_PREFIX . '*') ?: [] as $path) {
            if (!is_dir($path) || !str_starts_with(basename($path), self::SLUG_PREFIX)) {
                continue;
            }

            $this->recursiveRemoveDirectory($path);
        }
    }

    private function recursiveRemoveDirectory(string $path): void
    {
        $items = array_diff(scandir($path) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $child = $path . '/' . $item;
            is_dir($child) ? $this->recursiveRemoveDirectory($child) : unlink($child);
        }
        rmdir($path);
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare('SHOW TABLES LIKE :table_name');
        $stmt->execute(['table_name' => $table]);

        return (bool) $stmt->fetchColumn();
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }

        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE :column_name");
        $stmt->execute(['column_name' => $column]);

        return (bool) $stmt->fetchColumn();
    }
}

// End of file.
