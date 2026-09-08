<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Middleware\CurrentUser;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Auth\Session\SessionRepository;
use App\Platform\Auth\Session\SessionTokenService;
use App\Platform\Jobs\BackgroundJobRepository;
use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Social\SocialPermissionService;
use App\Tenant\Social\SocialRepository;
use PDO;

/**
 * Protects the uncertain provider boundary after Instagram returns a media ID.
 *
 * Once remote_post_id is present, the remote publication may already exist and
 * ArtsFolio must never allow content edits, cancellation, or a second publish.
 * Recovery is confirmation-only against the exact stored Instagram media ID.
 */
final class SocialConfirmationController
{
    private PDO $pdo;
    private SocialRepository $social;
    private CsrfTokenService $csrf;

    public function __construct(private readonly string $root)
    {
        $this->pdo = Database::connect($root);
        $this->social = new SocialRepository($this->pdo);
        $this->csrf = new CsrfTokenService();
    }

    public function handle(Request $request): ?Response
    {
        $path = $request->path();
        $isCompose = $request->method() === 'GET' && $path === '/admin/social/compose' && (int) ($_GET['post_id'] ?? 0) > 0;
        $isCancel = $request->method() === 'POST' && $path === '/admin/social/cancel';
        $isRetry = $request->method() === 'POST' && $path === '/admin/social/retry-confirmation';
        $isState = $request->method() === 'GET' && $path === '/admin/social/confirmation-state';
        if (!$isCompose && !$isCancel && !$isRetry && !$isState) {
            return null;
        }

        $tenant = (new TenantResolver($this->pdo))->resolveFromHost($request->host());
        if (!$tenant) {
            return Response::notFound();
        }
        $currentUser = (new CurrentUser(new SessionRepository($this->pdo), new SessionTokenService()))->resolve($request);
        $permissions = new SocialPermissionService($this->pdo);
        if (!$permissions->canPublish($currentUser, $tenant) || !$this->social->paidPlanAllowsSocial($tenant->tenantId)) {
            return Response::error(403, 'Social publishing permission required.');
        }

        if ($isState) {
            $stmt = $this->pdo->prepare(
                "SELECT sp.id, sp.status, sp.remote_post_id,
                        sc.status AS connection_status, sc.username
                 FROM social_posts sp
                 LEFT JOIN social_connections sc
                   ON sc.id = sp.social_connection_id
                  AND sc.tenant_id = sp.tenant_id
                 WHERE sp.tenant_id = :tenant_id
                   AND sp.remote_post_id IS NOT NULL
                   AND sp.remote_post_id <> ''
                   AND sp.status IN ('publishing','failed','authorization_required')
                 ORDER BY sp.id DESC
                 LIMIT 100"
            );
            $stmt->execute(['tenant_id' => $tenant->tenantId]);
            $posts = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $posts[] = [
                    'post_id' => (int) $row['id'],
                    'status' => (string) $row['status'],
                    'username' => (string) ($row['username'] ?? ''),
                    'can_retry' => (string) ($row['connection_status'] ?? '') === 'active'
                        && in_array((string) $row['status'], ['failed', 'authorization_required'], true),
                ];
            }
            return Response::json(['ok' => true, 'posts' => $posts], 200, ['Cache-Control' => 'private, no-store']);
        }

        $postId = max(0, (int) ($isCompose ? ($_GET['post_id'] ?? 0) : ($_POST['post_id'] ?? 0)));
        $post = $this->social->post($tenant->tenantId, $postId);
        if (!$post) {
            return $isCompose ? null : Response::error(404, 'Instagram post not found.');
        }
        $remoteId = trim((string) ($post['remote_post_id'] ?? ''));

        if ($isCompose) {
            if ($remoteId === '') {
                return null;
            }
            return Response::error(409, 'Instagram has already returned a media ID for this post. Its content can no longer be edited. Return to Instagram history and retry publication confirmation instead.');
        }

        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }

        if ($isCancel) {
            if ($remoteId === '') {
                return null;
            }
            return Response::error(409, 'This post may already exist on Instagram and cannot be canceled. Retry publication confirmation from Instagram history.');
        }

        if ($remoteId === '' || !in_array((string) $post['status'], ['failed', 'authorization_required'], true)) {
            return Response::error(409, 'This Instagram post is not awaiting publication confirmation.');
        }
        $connection = $this->social->connectionById($tenant->tenantId, (int) ($post['social_connection_id'] ?? 0));
        if (!$connection || (string) ($connection['status'] ?? '') !== 'active' || trim((string) ($connection['token_ciphertext'] ?? '')) === '') {
            return Response::error(422, 'Reconnect the Instagram account originally bound to this post before retrying confirmation.');
        }

        $userId = (int) ($currentUser['user_id'] ?? 0);
        $stmt = $this->pdo->prepare(
            "UPDATE social_posts
             SET status = 'scheduled',
                 scheduled_at = UTC_TIMESTAMP(),
                 next_attempt_at = UTC_TIMESTAMP(),
                 publish_attempts = 0,
                 last_error = NULL,
                 updated_by_user_id = :user_id,
                 updated_at = CURRENT_TIMESTAMP
             WHERE tenant_id = :tenant_id
               AND id = :id
               AND remote_post_id IS NOT NULL
               AND remote_post_id <> ''
               AND status IN ('failed','authorization_required')"
        );
        $stmt->execute(['user_id' => $userId, 'tenant_id' => $tenant->tenantId, 'id' => $postId]);
        if ($stmt->rowCount() !== 1) {
            return Response::error(409, 'Instagram confirmation state changed before it could be retried.');
        }

        (new BackgroundJobRepository($this->pdo))->enqueueSingleton(
            'social.publish_due',
            ['interval_seconds' => 60, 'batch_size' => 10],
            null,
            0,
        );

        return new Response('', 303, ['Location' => '/admin/social?notice=confirmation-retry-submitted']);
    }
}

// End of file.
