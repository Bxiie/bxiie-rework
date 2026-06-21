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
        $tenantCount = max(0, min(5000, $tenantCount));
        $artworksPerTenant = max(0, min(500, $artworksPerTenant));
        $eventsPerTenant = max(0, min(5000, $eventsPerTenant));

        $this->ensureFixtureImage();
        $fixtureSource = $this->root . '/storage/uploads/scale-fixtures/placeholder.png';
        $portfolioTypeId = $this->ensureArtworkType('portfolio_images', 'Portfolio Images');
        $createdOrUpdated = 0;

        for ($i = 1; $i <= $tenantCount; $i++) {
            $slug = self::SLUG_PREFIX . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $tenantId = $this->upsertTenant($slug, 'Scale Fixture Tenant ' . str_pad((string) $i, 4, '0', STR_PAD_LEFT));

            $this->upsertTenantDomain($tenantId, $slug . '.artsfol.io');
            $this->upsertTenantSetting($tenantId, self::MARKER_KEY, self::MARKER_VALUE);
            $this->upsertTenantSetting($tenantId, 'site_title', 'Scale Fixture Tenant ' . str_pad((string) $i, 4, '0', STR_PAD_LEFT));
            $this->upsertTenantSetting($tenantId, 'directory_opt_in', '0');
            $this->upsertTenantSetting($tenantId, 'scale_dataset_note', 'Synthetic tenant created by platform scale fixture tooling.');

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
        $this->deleteIfExists('tenant_plan_assignments', 'tenant_id');
        $this->deleteIfExists('tenant_memberships', 'tenant_id');
        $this->deleteIfExists('tenant_users', 'tenant_id');
        $this->deleteIfExists('role_assignments', 'tenant_id');
        $this->deleteIfExists('background_jobs', 'tenant_id');
        $this->deleteIfExists('tenant_domain_dns_results', 'tenant_id');
        $this->deleteIfExists('domain_artifacts', 'tenant_id');
        $this->deleteIfExists('tenant_domains', 'tenant_id');
        $this->deleteIfExists('tenant_settings', 'tenant_id');
        $this->deleteIfExists('artworks', 'tenant_id');
        $this->deleteIfExists('media_assets', 'tenant_id');
        $this->deleteIfExists('portfolio_sections', 'tenant_id');
        $this->deleteIfExists('audit_log', 'tenant_id');
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
            'tenants' => $tenantCount,
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

    private function deleteIfExists(string $table, string $column): void
    {
        if (!$this->tableExists($table) || !$this->columnExists($table, $column)) {
            return;
        }

        $this->pdo->exec("DELETE t FROM `{$table}` t JOIN scale_fixture_tenant_ids s ON s.tenant_id = t.`{$column}`");
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
