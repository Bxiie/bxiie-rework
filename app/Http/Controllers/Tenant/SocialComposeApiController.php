<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Middleware\CurrentUser;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Auth\Session\SessionRepository;
use App\Platform\Auth\Session\SessionTokenService;
use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;
use App\Tenant\Settings\TenantSettingsRepository;
use App\Tenant\Social\SocialPermissionService;
use App\Tenant\Social\SocialRepository;
use App\Tenant\Social\SocialTemplateRenderer;
use PDO;

/**
 * Read-only AJAX helpers used by Instagram Compose progressive enhancement.
 */
final class SocialComposeApiController
{
    private PDO $pdo;
    private SocialRepository $social;

    public function __construct(private readonly string $root)
    {
        $this->pdo = Database::connect($root);
        $this->social = new SocialRepository($this->pdo);
    }

    public function handle(Request $request): ?Response
    {
        $path = $request->path();
        if (!in_array($path, ['/admin/social/compose-state', '/admin/social/render-template'], true)) {
            return null;
        }
        if ($request->method() !== 'GET') {
            return Response::error(405, 'Method not allowed.');
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

        if ($path === '/admin/social/compose-state') {
            $postId = max(0, (int) ($_GET['post_id'] ?? 0));
            $post = $this->social->post($tenant->tenantId, $postId);
            if (
                !$post
                || trim((string) ($post['remote_post_id'] ?? '')) !== ''
                || !in_array((string) $post['status'], ['draft', 'scheduled', 'failed', 'authorization_required'], true)
            ) {
                return Response::error(404, 'Editable social post not found.');
            }
            $items = [];
            foreach ($this->social->postItems($tenant->tenantId, $postId) as $item) {
                $crop = json_decode((string) ($item['crop_json'] ?? '{}'), true);
                $items[] = [
                    'artwork_id' => (int) $item['artwork_id'],
                    'sort_order' => (int) $item['sort_order'],
                    'crop_mode' => (string) ($item['crop_mode'] ?? 'original'),
                    'crop_x' => max(0.0, min(1.0, (float) (is_array($crop) ? ($crop['x'] ?? 0.5) : 0.5))),
                    'crop_y' => max(0.0, min(1.0, (float) (is_array($crop) ? ($crop['y'] ?? 0.5) : 0.5))),
                ];
            }
            return Response::json(['ok' => true, 'items' => $items], 200, ['Cache-Control' => 'private, no-store']);
        }

        $artworkId = max(0, (int) ($_GET['artwork_id'] ?? 0));
        $templateId = max(0, (int) ($_GET['template_id'] ?? 0));
        $artwork = $this->social->artwork($tenant->tenantId, $artworkId);
        $template = $this->social->template($tenant->tenantId, $templateId);
        if (!$artwork || !$template) {
            return Response::error(404, 'Artwork or social template not found.');
        }
        $renderer = new SocialTemplateRenderer(new TenantSettingsRepository($this->pdo));
        $values = $renderer->values($tenant, $artwork, $tenant->hostname);
        return Response::json([
            'ok' => true,
            'caption' => $renderer->render((string) $template['template_body'], $values),
            'hashtags' => $values['hashtags'],
        ], 200, ['Cache-Control' => 'private, no-store']);
    }
}

// End of file.
