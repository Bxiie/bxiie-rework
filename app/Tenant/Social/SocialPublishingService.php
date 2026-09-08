<?php

declare(strict_types=1);

namespace App\Tenant\Social;

use App\Platform\Email\EmailOutboxRepository;
use PDO;
use Throwable;

/**
 * Publishes due provider-neutral social posts using the Instagram provider.
 */
final class SocialPublishingService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly SocialRepository $repository,
        private readonly SocialTokenCipher $cipher,
        private readonly InstagramClient $instagram,
        private readonly SocialImageService $images,
        private readonly EmailOutboxRepository $emailOutbox,
    ) {
    }

    /** @return array{checked:int,published:int,failed:int} */
    public function publishDue(int $limit = 10): array
    {
        $result = ['checked' => 0, 'published' => 0, 'failed' => 0];
        foreach ($this->repository->duePosts($limit) as $post) {
            $postId = (int) $post['id'];
            ++$result['checked'];
            if (!$this->repository->claimPost($postId)) {
                continue;
            }
            $claimed = $this->repository->post((int) $post['tenant_id'], $postId) ?? $post;
            try {
                $this->publishPost($claimed);
                ++$result['published'];
            } catch (Throwable $e) {
                ++$result['failed'];
                $this->handleFailure($claimed, $e);
            }
        }
        return $result;
    }

    private function publishPost(array $post): void
    {
        $postId = (int) $post['id'];
        $tenantId = (int) $post['tenant_id'];
        $attempt = max(1, (int) ($post['publish_attempts'] ?? 1));
        $connection = $this->repository->connection($tenantId, 'instagram');
        if (!$connection || (string) ($connection['status'] ?? '') !== 'active' || trim((string) ($connection['token_ciphertext'] ?? '')) === '') {
            throw new \RuntimeException('Instagram authorization is required before this post can be published.');
        }

        $token = $this->cipher->decrypt((string) $connection['token_ciphertext']);
        $igUserId = trim((string) ($connection['external_account_id'] ?? ''));
        if ($igUserId === '') {
            throw new \RuntimeException('Connected Instagram account ID is missing.');
        }

        $this->assertPublishingQuotaAvailable($igUserId, $token);

        $items = $this->repository->postItems($tenantId, $postId);
        if ($items === [] || count($items) > 10) {
            throw new \RuntimeException('Instagram posts require between one and ten images.');
        }

        $host = $this->tenantPublishingHost($tenantId);
        $containerIds = [];
        $carousel = count($items) > 1;
        foreach ($items as $item) {
            $derivativePath = trim((string) ($item['derivative_path'] ?? ''));
            if ($derivativePath === '' || !is_file($this->rootPath($derivativePath))) {
                $crop = json_decode((string) ($item['crop_json'] ?? '{}'), true);
                $derivative = $this->images->createDerivative(
                    $tenantId,
                    $postId,
                    (int) $item['id'],
                    $item,
                    (string) ($item['crop_mode'] ?? 'original'),
                    is_array($crop) ? $crop : [],
                );
                $derivativePath = $derivative['path'];
                $this->repository->updateItemDerivative($tenantId, (int) $item['id'], $derivativePath, $derivative['mime_type']);
            }

            $plainMediaToken = bin2hex(random_bytes(32));
            $expiresAt = gmdate('Y-m-d H:i:s', time() + 7200);
            $this->repository->createMediaToken($tenantId, (int) $item['id'], $plainMediaToken, $derivativePath, 'image/jpeg', $expiresAt);
            $mediaUrl = 'https://' . $host . '/social/media/' . rawurlencode($plainMediaToken);
            $containerId = $this->instagram->createImageContainer(
                $igUserId,
                $token,
                $mediaUrl,
                $carousel,
                $carousel ? null : $this->finalCaption($post),
            );
            $this->repository->updateItemContainer($tenantId, (int) $item['id'], $containerId);
            $containerIds[] = $containerId;
        }

        $creationId = count($containerIds) === 1
            ? $containerIds[0]
            : $this->instagram->createCarouselContainer($igUserId, $token, $containerIds, $this->finalCaption($post));
        $remoteId = $this->instagram->publishContainer($igUserId, $token, $creationId);
        $permalink = $this->instagram->permalink($remoteId, $token);
        $this->repository->markPublished($postId, $remoteId, $permalink);
        $this->repository->recordAttempt($postId, $attempt, 'published', null, 'Instagram publication confirmed.', ['remote_post_id' => $remoteId, 'permalink' => $permalink]);
        $this->queueNotification($tenantId, $post, true, 'Published to Instagram', $permalink ?: 'Instagram publication completed.');
    }

    private function assertPublishingQuotaAvailable(string $igUserId, string $token): void
    {
        $limit = $this->instagram->publishingLimit($igUserId, $token);
        $row = is_array($limit['data'][0] ?? null) ? $limit['data'][0] : (is_array($limit) ? $limit : []);
        $usage = isset($row['quota_usage']) ? (int) $row['quota_usage'] : null;
        $config = is_array($row['config'] ?? null) ? $row['config'] : [];
        $total = isset($config['quota_total']) ? (int) $config['quota_total'] : null;
        if ($usage !== null && $total !== null && $total > 0 && $usage >= $total) {
            throw new InstagramApiException(
                'Instagram API publishing limit has been reached. ArtsFolio will retry automatically.',
                'publishing_limit',
                429,
                $limit ?? [],
            );
        }
    }

    private function handleFailure(array $post, Throwable $e): void
    {
        $postId = (int) $post['id'];
        $tenantId = (int) $post['tenant_id'];
        $attempt = max(1, (int) ($post['publish_attempts'] ?? 1));
        $authorization = $e instanceof InstagramApiException ? $e->authorizationFailure() : str_contains(strtolower($e->getMessage()), 'authorization');
        $retryable = $e instanceof InstagramApiException ? $e->retryable() : $this->looksLikeTransientNetworkFailure($e);
        $providerCode = $e instanceof InstagramApiException ? $e->providerCode : null;
        $providerResponse = $e instanceof InstagramApiException ? $this->safeProviderResponse($e->providerResponse) : null;
        $shouldRetry = $retryable && $attempt < 4;
        $this->repository->markPublishFailure($postId, $e->getMessage(), $authorization, $shouldRetry);
        $this->repository->recordAttempt($postId, $attempt, $authorization ? 'authorization_required' : ($shouldRetry ? 'retrying' : 'failed'), $providerCode, $e->getMessage(), $providerResponse);
        if ($authorization || !$shouldRetry) {
            $this->queueNotification($tenantId, $post, false, 'Instagram publication failed', $e->getMessage());
        }
    }

    private function looksLikeTransientNetworkFailure(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        foreach (['network request failed', 'timed out', 'timeout', 'could not resolve host', 'connection reset', 'connection refused'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function finalCaption(array $post): string
    {
        $caption = trim((string) ($post['caption'] ?? ''));
        $hashtags = trim((string) ($post['hashtags'] ?? ''));
        if ($hashtags !== '' && !str_contains($caption, $hashtags)) {
            $caption = trim($caption . "\n\n" . $hashtags);
        }
        return $caption;
    }

    private function tenantPublishingHost(int $tenantId): string
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE((SELECT d.hostname FROM tenant_domains d WHERE d.tenant_id = t.id AND d.status = 'active' AND d.is_primary = 1 ORDER BY d.id LIMIT 1), CONCAT(t.slug, '.artsfol.io')) FROM tenants t WHERE t.id = :tenant_id LIMIT 1");
        $stmt->execute(['tenant_id' => $tenantId]);
        $host = strtolower(trim((string) $stmt->fetchColumn()));
        if ($host === '' || !preg_match('/^[a-z0-9.-]+$/', $host)) {
            throw new \RuntimeException('Tenant publishing hostname could not be resolved.');
        }
        return $host;
    }

    private function queueNotification(int $tenantId, array $post, bool $success, string $subject, string $detail): void
    {
        foreach ($this->notificationRecipients($tenantId) as $recipient) {
            $artworkTitle = trim((string) ($this->artworkTitle((int) ($post['source_artwork_id'] ?? 0)) ?: 'Artwork'));
            $body = $success
                ? "{$artworkTitle} was published to Instagram.\n\n{$detail}\n\nOpen ArtsFolio Instagram history: https://" . $this->tenantPublishingHost($tenantId) . '/admin/social'
                : "ArtsFolio could not publish {$artworkTitle} to Instagram.\n\nReason: {$detail}\n\nOpen Instagram history: https://" . $this->tenantPublishingHost($tenantId) . '/admin/social';
            $this->emailOutbox->queue(
                (string) $recipient['email'],
                $subject,
                $body,
                null,
                ($recipient['display_name'] ?? null) !== null ? (string) $recipient['display_name'] : null,
                $tenantId,
                (int) $recipient['id'],
                'social.instagram.status',
            );
        }
    }

    private function notificationRecipients(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT u.id, u.email, u.display_name
             FROM tenant_memberships tm
             JOIN users u ON u.id = tm.user_id
             LEFT JOIN role_assignments ra ON ra.user_id = u.id AND ra.tenant_id = tm.tenant_id
             LEFT JOIN roles r ON r.id = ra.role_id AND r.scope = 'tenant'
             LEFT JOIN tenant_user_permissions tup ON tup.tenant_id = tm.tenant_id AND tup.user_id = u.id AND tup.permission_key = 'social.publish'
             WHERE tm.tenant_id = :tenant_id
               AND tm.status = 'active'
               AND (r.slug IN ('owner','admin','tenant_owner','tenant_admin') OR (r.slug = 'editor' AND tup.user_id IS NOT NULL))
             ORDER BY u.id"
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function artworkTitle(int $artworkId): ?string
    {
        if ($artworkId < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT title FROM artworks WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $artworkId]);
        $title = $stmt->fetchColumn();
        return $title !== false ? (string) $title : null;
    }

    private function rootPath(string $relativePath): string
    {
        return dirname(__DIR__, 3) . '/' . ltrim($relativePath, '/');
    }

    private function safeProviderResponse(array $response): array
    {
        unset($response['access_token']);
        if (isset($response['error']) && is_array($response['error'])) {
            unset($response['error']['fbtrace_id']);
        }
        return $response;
    }
}

// End of file.
