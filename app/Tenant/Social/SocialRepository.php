<?php

declare(strict_types=1);

namespace App\Tenant\Social;

use PDO;

/**
 * Tenant-scoped persistence for social connections, templates, posts, media, and history.
 */
final class SocialRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function paidPlanAllowsSocial(int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.slug
             FROM tenant_plan_assignments tpa
             JOIN plans p ON p.id = tpa.plan_id
             WHERE tpa.tenant_id = :tenant_id
               AND tpa.status IN ('trial','active','manual')
             ORDER BY tpa.id DESC
             LIMIT 1"
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        return in_array((string) $stmt->fetchColumn(), ['studio', 'pro', 'collective'], true);
    }

    /** Returns the provider account currently selected for new posts. */
    public function connection(int $tenantId, string $provider = 'instagram'): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM social_connections WHERE tenant_id = :tenant_id AND provider = :provider AND is_default = 1 ORDER BY id DESC LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'provider' => $provider]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Returns an exact tenant-owned connection used by an already-scheduled post. */
    public function connectionById(int $tenantId, int $connectionId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM social_connections WHERE tenant_id = :tenant_id AND id = :id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $connectionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Creates or refreshes one provider account and makes it the default for new posts.
     * Existing scheduled posts remain bound to their original social_connection_id.
     */
    public function saveConnection(int $tenantId, string $provider, string $externalAccountId, ?string $username, string $tokenCiphertext, ?string $expiresAt, string $scopes, int $userId): int
    {
        $this->pdo->beginTransaction();
        try {
            $clear = $this->pdo->prepare('UPDATE social_connections SET is_default = 0, updated_at = CURRENT_TIMESTAMP WHERE tenant_id = :tenant_id AND provider = :provider AND is_default = 1');
            $clear->execute(['tenant_id' => $tenantId, 'provider' => $provider]);

            $find = $this->pdo->prepare('SELECT id FROM social_connections WHERE tenant_id = :tenant_id AND provider = :provider AND external_account_id = :external_account_id LIMIT 1 FOR UPDATE');
            $find->execute(['tenant_id' => $tenantId, 'provider' => $provider, 'external_account_id' => $externalAccountId]);
            $existingId = $find->fetchColumn();

            if ($existingId !== false) {
                $connectionId = (int) $existingId;
                $stmt = $this->pdo->prepare(
                    "UPDATE social_connections
                     SET username = :username,
                         token_ciphertext = :token_ciphertext,
                         token_expires_at = :token_expires_at,
                         granted_scopes = :granted_scopes,
                         status = 'active',
                         is_default = 1,
                         last_error = NULL,
                         connected_by_user_id = :connected_by_user_id,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE tenant_id = :tenant_id AND id = :id"
                );
                $stmt->execute([
                    'username' => $username,
                    'token_ciphertext' => $tokenCiphertext,
                    'token_expires_at' => $expiresAt,
                    'granted_scopes' => $scopes,
                    'connected_by_user_id' => $userId,
                    'tenant_id' => $tenantId,
                    'id' => $connectionId,
                ]);
            } else {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO social_connections (
                        tenant_id, provider, external_account_id, username, token_ciphertext,
                        token_expires_at, granted_scopes, status, is_default, connected_by_user_id,
                        created_at, updated_at
                     ) VALUES (
                        :tenant_id, :provider, :external_account_id, :username, :token_ciphertext,
                        :token_expires_at, :granted_scopes, 'active', 1, :connected_by_user_id,
                        CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                     )"
                );
                $stmt->execute([
                    'tenant_id' => $tenantId,
                    'provider' => $provider,
                    'external_account_id' => $externalAccountId,
                    'username' => $username,
                    'token_ciphertext' => $tokenCiphertext,
                    'token_expires_at' => $expiresAt,
                    'granted_scopes' => $scopes,
                    'connected_by_user_id' => $userId,
                ]);
                $connectionId = (int) $this->pdo->lastInsertId();
            }

            $this->pdo->commit();
            return $connectionId;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Disconnects only the currently selected account, without changing posts bound to other accounts. */
    public function disconnect(int $tenantId, string $provider = 'instagram'): void
    {
        $connection = $this->connection($tenantId, $provider);
        if (!$connection) {
            return;
        }
        $connectionId = (int) $connection['id'];
        $stmt = $this->pdo->prepare("UPDATE social_connections SET status = 'revoked', is_default = 0, token_ciphertext = '', updated_at = CURRENT_TIMESTAMP WHERE tenant_id = :tenant_id AND id = :id");
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $connectionId]);
        $pending = $this->pdo->prepare("UPDATE social_posts SET status = 'authorization_required', next_attempt_at = NULL, last_error = 'Instagram account disconnected.', updated_at = CURRENT_TIMESTAMP WHERE tenant_id = :tenant_id AND social_connection_id = :connection_id AND status IN ('draft','scheduled','failed')");
        $pending->execute(['tenant_id' => $tenantId, 'connection_id' => $connectionId]);
    }

    public function templates(int $tenantId, string $provider = 'instagram'): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM social_templates WHERE tenant_id = :tenant_id AND provider = :provider ORDER BY is_default DESC, LOWER(name), id');
        $stmt->execute(['tenant_id' => $tenantId, 'provider' => $provider]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function template(int $tenantId, int $templateId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM social_templates WHERE tenant_id = :tenant_id AND id = :id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $templateId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function ensureDefaultTemplate(int $tenantId): int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM social_templates WHERE tenant_id = :tenant_id AND provider = 'instagram' AND is_default = 1 ORDER BY id LIMIT 1");
        $stmt->execute(['tenant_id' => $tenantId]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            return (int) $existing;
        }

        $body = "{title}\n{year_medium_dimensions}\n\n{social_or_description}\n\n{artwork_url}\n\n{hashtags}";
        $insert = $this->pdo->prepare("INSERT INTO social_templates (tenant_id, provider, name, template_body, is_default, created_at, updated_at) VALUES (:tenant_id, 'instagram', 'Default', :template_body, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        $insert->execute(['tenant_id' => $tenantId, 'template_body' => $body]);
        return (int) $this->pdo->lastInsertId();
    }

    public function saveTemplate(int $tenantId, ?int $id, string $name, string $body, bool $isDefault): int
    {
        $this->pdo->beginTransaction();
        try {
            if ($isDefault) {
                $clear = $this->pdo->prepare("UPDATE social_templates SET is_default = 0 WHERE tenant_id = :tenant_id AND provider = 'instagram'");
                $clear->execute(['tenant_id' => $tenantId]);
            }
            if ($id !== null && $id > 0) {
                $stmt = $this->pdo->prepare("UPDATE social_templates SET name = :name, template_body = :body, is_default = :is_default, updated_at = CURRENT_TIMESTAMP WHERE tenant_id = :tenant_id AND id = :id");
                $stmt->execute(['name' => $name, 'body' => $body, 'is_default' => $isDefault ? 1 : 0, 'tenant_id' => $tenantId, 'id' => $id]);
                $this->pdo->commit();
                return $id;
            }
            $stmt = $this->pdo->prepare("INSERT INTO social_templates (tenant_id, provider, name, template_body, is_default, created_at, updated_at) VALUES (:tenant_id, 'instagram', :name, :body, :is_default, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $stmt->execute(['tenant_id' => $tenantId, 'name' => $name, 'body' => $body, 'is_default' => $isDefault ? 1 : 0]);
            $id = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function deleteTemplate(int $tenantId, int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM social_templates WHERE tenant_id = :tenant_id AND id = :id AND is_default = 0');
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $id]);
    }

    public function sectionAssignments(int $tenantId): array
    {
        $stmt = $this->pdo->prepare("SELECT sst.section_id, sst.template_id, ps.name AS section_name, st.name AS template_name FROM social_section_templates sst JOIN portfolio_sections ps ON ps.id = sst.section_id AND ps.tenant_id = sst.tenant_id JOIN social_templates st ON st.id = sst.template_id AND st.tenant_id = sst.tenant_id WHERE sst.tenant_id = :tenant_id AND sst.provider = 'instagram' ORDER BY LOWER(ps.name)");
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function setSectionTemplate(int $tenantId, int $sectionId, ?int $templateId): void
    {
        if ($templateId === null || $templateId < 1) {
            $stmt = $this->pdo->prepare("DELETE FROM social_section_templates WHERE tenant_id = :tenant_id AND section_id = :section_id AND provider = 'instagram'");
            $stmt->execute(['tenant_id' => $tenantId, 'section_id' => $sectionId]);
            return;
        }
        $stmt = $this->pdo->prepare("INSERT INTO social_section_templates (tenant_id, section_id, provider, template_id, created_at) VALUES (:tenant_id, :section_id, 'instagram', :template_id, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE template_id = VALUES(template_id)");
        $stmt->execute(['tenant_id' => $tenantId, 'section_id' => $sectionId, 'template_id' => $templateId]);
    }

    public function portfolioSections(int $tenantId): array
    {
        $stmt = $this->pdo->prepare("SELECT id, name FROM portfolio_sections WHERE tenant_id = :tenant_id AND status <> 'archived' ORDER BY LOWER(name), id");
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function artwork(int $tenantId, int $artworkId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.*, m.id AS media_id, m.uuid AS media_uuid, m.storage_path, m.mime_type, m.width, m.height, m.alt_text,
                    (SELECT GROUP_CONCAT(ps.id ORDER BY ps.id) FROM artwork_section_assignments asa JOIN portfolio_sections ps ON ps.id = asa.section_id WHERE asa.artwork_id = a.id AND ps.tenant_id = a.tenant_id) AS section_ids,
                    (SELECT GROUP_CONCAT(ps.name ORDER BY LOWER(ps.name) SEPARATOR '||') FROM artwork_section_assignments asa JOIN portfolio_sections ps ON ps.id = asa.section_id WHERE asa.artwork_id = a.id AND ps.tenant_id = a.tenant_id) AS section_names
             FROM artworks a LEFT JOIN media_assets m ON m.id = a.primary_media_id
             WHERE a.tenant_id = :tenant_id AND a.id = :id AND a.status <> 'archived' LIMIT 1"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $artworkId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function artworkBySlug(int $tenantId, string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM artworks WHERE tenant_id = :tenant_id AND slug = :slug AND status <> "archived" LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'slug' => $slug]);
        $id = $stmt->fetchColumn();
        return $id ? $this->artwork($tenantId, (int) $id) : null;
    }

    public function carouselCandidates(int $tenantId, int $sourceArtworkId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.id, a.title, a.slug, a.primary_media_id AS media_id, m.uuid AS media_uuid,
                    CASE WHEN EXISTS (
                        SELECT 1 FROM artwork_section_assignments mine
                        JOIN artwork_section_assignments theirs ON theirs.section_id = mine.section_id
                        WHERE mine.artwork_id = :source_artwork_id AND theirs.artwork_id = a.id
                    ) THEN 0 ELSE 1 END AS section_rank
             FROM artworks a
             JOIN media_assets m ON m.id = a.primary_media_id AND m.tenant_id = a.tenant_id
             WHERE a.tenant_id = :tenant_id AND a.status <> 'archived' AND m.is_private = 0
             ORDER BY section_rank ASC, LOWER(a.title), a.id
             LIMIT 500"
        );
        $stmt->execute(['source_artwork_id' => $sourceArtworkId, 'tenant_id' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function effectiveTemplate(int $tenantId, array $artwork): array
    {
        $this->ensureDefaultTemplate($tenantId);
        $sectionIds = array_values(array_filter(array_map('intval', explode(',', (string) ($artwork['section_ids'] ?? '')))));
        if (count($sectionIds) === 1) {
            $stmt = $this->pdo->prepare("SELECT st.* FROM social_section_templates sst JOIN social_templates st ON st.id = sst.template_id WHERE sst.tenant_id = :tenant_id AND sst.provider = 'instagram' AND sst.section_id = :section_id LIMIT 1");
            $stmt->execute(['tenant_id' => $tenantId, 'section_id' => $sectionIds[0]]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }
        $stmt = $this->pdo->prepare("SELECT * FROM social_templates WHERE tenant_id = :tenant_id AND provider = 'instagram' AND is_default = 1 ORDER BY id LIMIT 1");
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveArtworkSocialMetadata(int $tenantId, int $artworkId, string $caption, string $hashtags): void
    {
        $stmt = $this->pdo->prepare('UPDATE artworks SET social_caption = :caption, social_hashtags = :hashtags, updated_at = CURRENT_TIMESTAMP WHERE tenant_id = :tenant_id AND id = :id');
        $stmt->execute(['caption' => $caption !== '' ? $caption : null, 'hashtags' => $hashtags !== '' ? $hashtags : null, 'tenant_id' => $tenantId, 'id' => $artworkId]);
    }

    public function createPost(int $tenantId, int $socialConnectionId, int $sourceArtworkId, int $templateId, string $caption, string $hashtags, string $status, ?string $scheduledAt, array $snapshot, int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO social_posts (uuid, tenant_id, provider, social_connection_id, source_artwork_id, template_id, caption, hashtags, snapshot_json, status, scheduled_at, next_attempt_at, created_by_user_id, updated_by_user_id, created_at, updated_at)
             VALUES (:uuid, :tenant_id, 'instagram', :social_connection_id, :source_artwork_id, :template_id, :caption, :hashtags, :snapshot_json, :status, :scheduled_at, :next_attempt_at, :user_id, :user_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
        );
        $stmt->execute([
            'uuid' => $this->uuidV4(), 'tenant_id' => $tenantId, 'social_connection_id' => $socialConnectionId,
            'source_artwork_id' => $sourceArtworkId, 'template_id' => $templateId, 'caption' => $caption, 'hashtags' => $hashtags,
            'snapshot_json' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'status' => $status,
            'scheduled_at' => $scheduledAt, 'next_attempt_at' => $scheduledAt, 'user_id' => $userId,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function addPostItem(int $postId, int $tenantId, int $artworkId, int $mediaId, int $sortOrder, string $cropMode, array $crop): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO social_post_items (social_post_id, tenant_id, artwork_id, media_asset_id, sort_order, crop_mode, crop_json, created_at) VALUES (:post_id, :tenant_id, :artwork_id, :media_id, :sort_order, :crop_mode, :crop_json, CURRENT_TIMESTAMP)");
        $stmt->execute(['post_id' => $postId, 'tenant_id' => $tenantId, 'artwork_id' => $artworkId, 'media_id' => $mediaId, 'sort_order' => $sortOrder, 'crop_mode' => $cropMode, 'crop_json' => json_encode($crop, JSON_THROW_ON_ERROR)]);
        return (int) $this->pdo->lastInsertId();
    }

    public function history(int $tenantId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare("SELECT sp.*, a.title AS artwork_title, sc.username AS instagram_username, sc.external_account_id AS instagram_account_id FROM social_posts sp LEFT JOIN artworks a ON a.id = sp.source_artwork_id AND a.tenant_id = sp.tenant_id LEFT JOIN social_connections sc ON sc.id = sp.social_connection_id AND sc.tenant_id = sp.tenant_id WHERE sp.tenant_id = :tenant_id ORDER BY sp.created_at DESC, sp.id DESC LIMIT :limit_count");
        $stmt->bindValue('tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindValue('limit_count', max(1, min(500, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function publishedPostCountForArtwork(int $tenantId, int $artworkId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT sp.id) FROM social_posts sp WHERE sp.tenant_id = :tenant_id AND sp.status = 'published' AND sp.remote_post_id IS NOT NULL AND sp.remote_post_id <> '' AND (sp.source_artwork_id = :source_artwork_id OR EXISTS (SELECT 1 FROM social_post_items spi WHERE spi.social_post_id = sp.id AND spi.tenant_id = sp.tenant_id AND spi.artwork_id = :item_artwork_id))");
        $stmt->execute(['tenant_id' => $tenantId, 'source_artwork_id' => $artworkId, 'item_artwork_id' => $artworkId]);
        return (int) $stmt->fetchColumn();
    }

    public function post(int $tenantId, int $postId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM social_posts WHERE tenant_id = :tenant_id AND id = :id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $postId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function postItems(int $tenantId, int $postId): array
    {
        $stmt = $this->pdo->prepare("SELECT spi.*, m.uuid AS media_uuid, m.storage_path, m.mime_type, m.width, m.height, a.title AS artwork_title FROM social_post_items spi JOIN media_assets m ON m.id = spi.media_asset_id AND m.tenant_id = spi.tenant_id JOIN artworks a ON a.id = spi.artwork_id AND a.tenant_id = spi.tenant_id WHERE spi.tenant_id = :tenant_id AND spi.social_post_id = :post_id ORDER BY spi.sort_order, spi.id");
        $stmt->execute(['tenant_id' => $tenantId, 'post_id' => $postId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function cancelPost(int $tenantId, int $postId, int $userId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE social_posts SET status = 'cancelled', next_attempt_at = NULL, updated_by_user_id = :user_id, updated_at = CURRENT_TIMESTAMP WHERE tenant_id = :tenant_id AND id = :id AND status IN ('draft','scheduled','failed','authorization_required') AND (remote_post_id IS NULL OR remote_post_id = '')");
        $stmt->execute(['user_id' => $userId, 'tenant_id' => $tenantId, 'id' => $postId]);
        return $stmt->rowCount() === 1;
    }

    public function duePosts(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM social_posts WHERE ((status = 'scheduled' AND scheduled_at <= UTC_TIMESTAMP()) OR (status = 'failed' AND next_attempt_at IS NOT NULL AND next_attempt_at <= UTC_TIMESTAMP())) ORDER BY COALESCE(next_attempt_at, scheduled_at), id LIMIT :limit_count");
        $stmt->bindValue('limit_count', max(1, min(50, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function claimPost(int $postId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE social_posts SET status = 'publishing', publish_attempts = publish_attempts + 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND ((status = 'scheduled' AND scheduled_at <= UTC_TIMESTAMP()) OR (status = 'failed' AND next_attempt_at IS NOT NULL AND next_attempt_at <= UTC_TIMESTAMP()))");
        $stmt->execute(['id' => $postId]);
        return $stmt->rowCount() === 1;
    }

    public function updateItemDerivative(int $tenantId, int $itemId, string $path, string $mimeType): void
    {
        $stmt = $this->pdo->prepare('UPDATE social_post_items SET derivative_path = :path, derivative_mime_type = :mime_type WHERE tenant_id = :tenant_id AND id = :id');
        $stmt->execute(['path' => $path, 'mime_type' => $mimeType, 'tenant_id' => $tenantId, 'id' => $itemId]);
    }

    public function updateItemContainer(int $tenantId, int $itemId, string $containerId): void
    {
        $stmt = $this->pdo->prepare('UPDATE social_post_items SET remote_container_id = :container_id WHERE tenant_id = :tenant_id AND id = :id');
        $stmt->execute(['container_id' => $containerId, 'tenant_id' => $tenantId, 'id' => $itemId]);
    }

    public function createMediaToken(int $tenantId, int $itemId, string $plainToken, string $path, string $mimeType, string $expiresAt): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO social_media_tokens (tenant_id, social_post_item_id, token_hash, storage_path, mime_type, expires_at, created_at) VALUES (:tenant_id, :item_id, :token_hash, :path, :mime_type, :expires_at, CURRENT_TIMESTAMP)');
        $stmt->execute(['tenant_id' => $tenantId, 'item_id' => $itemId, 'token_hash' => hash('sha256', $plainToken), 'path' => $path, 'mime_type' => $mimeType, 'expires_at' => $expiresAt]);
    }

    public function mediaToken(string $plainToken): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM social_media_tokens WHERE token_hash = :token_hash AND expires_at > UTC_TIMESTAMP() LIMIT 1');
        $stmt->execute(['token_hash' => hash('sha256', $plainToken)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Stores Meta's publication ID before any follow-up request can fail. */
    public function rememberRemotePostId(int $postId, string $remoteId): void
    {
        $stmt = $this->pdo->prepare("UPDATE social_posts SET remote_post_id = :remote_id, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND status = 'publishing'");
        $stmt->execute(['remote_id' => $remoteId, 'id' => $postId]);
    }

    public function markPublished(int $postId, string $remoteId, ?string $permalink): void
    {
        $stmt = $this->pdo->prepare("UPDATE social_posts SET status = 'published', remote_post_id = :remote_id, remote_permalink = :permalink, published_at = UTC_TIMESTAMP(), last_error = NULL, next_attempt_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND status = 'publishing'");
        $stmt->execute(['remote_id' => $remoteId, 'permalink' => $permalink, 'id' => $postId]);
    }

    public function markPublishFailure(int $postId, string $message, bool $authorization, bool $retry): void
    {
        $status = $authorization ? 'authorization_required' : 'failed';
        $next = $retry ? 'DATE_ADD(UTC_TIMESTAMP(), INTERVAL LEAST(60, POW(2, publish_attempts)) MINUTE)' : 'NULL';
        $stmt = $this->pdo->prepare("UPDATE social_posts SET status = '{$status}', last_error = :message, next_attempt_at = {$next}, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND status = 'publishing'");
        $stmt->execute(['message' => $message, 'id' => $postId]);
    }

    public function recordAttempt(int $postId, int $attemptNumber, string $status, ?string $providerCode, string $message, ?array $response = null): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO social_publish_attempts (social_post_id, attempt_number, status, provider_code, message, response_json, created_at) VALUES (:post_id, :attempt_number, :status, :provider_code, :message, :response_json, CURRENT_TIMESTAMP)');
        $stmt->execute(['post_id' => $postId, 'attempt_number' => $attemptNumber, 'status' => $status, 'provider_code' => $providerCode, 'message' => $message, 'response_json' => $response !== null ? json_encode($response, JSON_THROW_ON_ERROR) : null]);
    }

    private function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

// End of file.
