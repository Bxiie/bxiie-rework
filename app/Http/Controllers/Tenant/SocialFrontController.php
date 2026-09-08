<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Middleware\CurrentUser;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Auth\Session\SessionRepository;
use App\Platform\Auth\Session\SessionTokenService;
use App\Platform\Email\EmailOutboxRepository;
use App\Platform\Tenancy\TenantContext;
use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;
use App\Support\Security\CsrfTokenService;
use App\Support\Time\UserTimezoneContext;
use App\Tenant\Settings\TenantSettingsRepository;
use App\Tenant\Social\InstagramClient;
use App\Tenant\Social\SocialPermissionService;
use App\Tenant\Social\SocialRepository;
use App\Tenant\Social\SocialTemplateRenderer;
use App\Tenant\Social\SocialTokenCipher;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

/**
 * Isolated tenant social-publishing route surface.
 *
 * Keeping these routes behind the existing public front controller avoids
 * duplicating ArtsFolio's large tenant route registrar while the provider-neutral
 * social subsystem remains independently testable.
 */
final class SocialFrontController
{
    private PDO $pdo;
    private SocialRepository $social;
    private SocialPermissionService $permissions;
    private TenantSettingsRepository $settings;
    private CsrfTokenService $csrf;

    public function __construct(private readonly string $root)
    {
        $this->pdo = Database::connect($root);
        $this->social = new SocialRepository($this->pdo);
        $this->permissions = new SocialPermissionService($this->pdo);
        $this->settings = new TenantSettingsRepository($this->pdo);
        $this->csrf = new CsrfTokenService();
    }

    public function handle(Request $request): ?Response
    {
        $path = $request->path();
        if ($path === '/social/instagram/callback') {
            return $this->oauthCallback($request);
        }
        if (preg_match('#^/social/media/([a-f0-9]{64})$#', $path, $matches) === 1) {
            return $this->media($request, $matches[1]);
        }
        if (!str_starts_with($path, '/admin/social') && $path !== '/social/context') {
            return null;
        }

        $tenant = (new TenantResolver($this->pdo))->resolveFromHost($request->host());
        if (!$tenant) {
            return Response::notFound();
        }
        $currentUser = (new CurrentUser(new SessionRepository($this->pdo), new SessionTokenService()))->resolve($request);
        UserTimezoneContext::apply($this->pdo, $currentUser);

        if ($path === '/social/context') {
            return $this->context($tenant, $currentUser);
        }
        if (!$this->permissions->canPublish($currentUser, $tenant)) {
            if (!$currentUser) {
                return new Response('', 303, ['Location' => '/login?notice=admin-login-required']);
            }
            return Response::error(403, 'Social publishing permission required.');
        }
        if (!$this->social->paidPlanAllowsSocial($tenant->tenantId)) {
            return Response::error(403, 'Instagram publishing is available on Studio, Professional, and Collective plans.');
        }

        return match ([$request->method(), $path]) {
            ['GET', '/admin/social'] => $this->settingsPage($tenant, $currentUser),
            ['POST', '/admin/social/defaults'] => $this->saveDefaults($tenant),
            ['POST', '/admin/social/template'] => $this->saveTemplate($tenant),
            ['POST', '/admin/social/template/delete'] => $this->deleteTemplate($tenant),
            ['POST', '/admin/social/section-template'] => $this->saveSectionTemplate($tenant),
            ['GET', '/admin/social/connect'] => $this->connect($tenant, $currentUser),
            ['POST', '/admin/social/disconnect'] => $this->disconnect($tenant),
            ['GET', '/admin/social/compose'] => $this->compose($tenant, $currentUser),
            ['POST', '/admin/social/post'] => $this->submitPost($tenant, $currentUser),
            ['POST', '/admin/social/cancel'] => $this->cancelPost($tenant, $currentUser),
            ['POST', '/admin/social/artwork-metadata'] => $this->saveArtworkMetadata($tenant),
            default => Response::notFound(),
        };
    }

    private function context(TenantContext $tenant, ?array $currentUser): Response
    {
        $allowed = $this->permissions->canPublish($currentUser, $tenant) && $this->social->paidPlanAllowsSocial($tenant->tenantId);
        $artwork = null;
        $artworkId = max(0, (int) ($_GET['artwork_id'] ?? 0));
        $slug = trim((string) ($_GET['artwork_slug'] ?? ''));
        if ($allowed) {
            $artwork = $artworkId > 0 ? $this->social->artwork($tenant->tenantId, $artworkId) : ($slug !== '' ? $this->social->artworkBySlug($tenant->tenantId, $slug) : null);
        }
        $connection = $allowed ? $this->social->connection($tenant->tenantId) : null;
        return Response::json([
            'ok' => true,
            'allowed' => $allowed,
            'connected' => $connection && (string) ($connection['status'] ?? '') === 'active',
            'artwork_id' => $artwork ? (int) $artwork['id'] : null,
            'social_caption' => $artwork ? (string) ($artwork['social_caption'] ?? '') : '',
            'social_hashtags' => $artwork ? (string) ($artwork['social_hashtags'] ?? '') : '',
            'compose_url' => $artwork ? '/admin/social/compose?artwork_id=' . (int) $artwork['id'] : null,
        ], 200, ['Cache-Control' => 'private, no-store']);
    }

    private function settingsPage(TenantContext $tenant, ?array $currentUser): Response
    {
        $this->social->ensureDefaultTemplate($tenant->tenantId);
        $connection = $this->social->connection($tenant->tenantId);
        $templates = $this->social->templates($tenant->tenantId);
        $sections = $this->social->portfolioSections($tenant->tenantId);
        $assignments = [];
        foreach ($this->social->sectionAssignments($tenant->tenantId) as $assignment) {
            $assignments[(int) $assignment['section_id']] = (int) $assignment['template_id'];
        }
        $csrf = $this->e($this->csrf->getOrCreate());
        $defaultHashtags = $this->e((string) $this->settings->get($tenant, 'instagram_default_hashtags', ''));
        $connectionHtml = $connection && (string) ($connection['status'] ?? '') === 'active'
            ? '<p><strong>Connected:</strong> @' . $this->e((string) ($connection['username'] ?? 'Instagram professional account')) . '</p><form method="post" action="/admin/social/disconnect"><input type="hidden" name="csrf_token" value="' . $csrf . '"><button type="submit">Disconnect Instagram</button></form>'
            : '<p>No Instagram Creator/Business account is connected.</p><p><a class="admin-button" href="/admin/social/connect">Connect Instagram</a></p>';

        $templateCards = '';
        foreach ($templates as $template) {
            $id = (int) $template['id'];
            $name = $this->e((string) $template['name']);
            $body = $this->e((string) $template['template_body']);
            $checked = (int) $template['is_default'] === 1 ? ' checked' : '';
            $delete = (int) $template['is_default'] === 1 ? '' : '<form method="post" action="/admin/social/template/delete" onsubmit="return confirm(\'Delete this template?\');"><input type="hidden" name="csrf_token" value="' . $csrf . '"><input type="hidden" name="template_id" value="' . $id . '"><button type="submit">Delete</button></form>';
            $templateCards .= '<article class="admin-card"><form method="post" action="/admin/social/template"><input type="hidden" name="csrf_token" value="' . $csrf . '"><input type="hidden" name="template_id" value="' . $id . '"><label>Name<br><input name="name" value="' . $name . '" required></label><label>Template<br><textarea name="template_body" rows="8" required>' . $body . '</textarea></label><label><input type="checkbox" name="is_default" value="1"' . $checked . '> Tenant default</label><p><button type="submit">Save template</button></p></form>' . $delete . '</article>';
        }
        $templateCards .= '<article class="admin-card"><h3>Add template</h3><form method="post" action="/admin/social/template"><input type="hidden" name="csrf_token" value="' . $csrf . '"><label>Name<br><input name="name" required></label><label>Template<br><textarea name="template_body" rows="8" required>{title}\n{year_medium_dimensions}\n\n{social_or_description}\n\n{artwork_url}\n\n{hashtags}</textarea></label><p><button type="submit">Add template</button></p></form></article>';

        $sectionRows = '';
        foreach ($sections as $section) {
            $sectionId = (int) $section['id'];
            $options = '<option value="">Tenant default</option>';
            foreach ($templates as $template) {
                $tid = (int) $template['id'];
                $selected = ($assignments[$sectionId] ?? 0) === $tid ? ' selected' : '';
                $options .= '<option value="' . $tid . '"' . $selected . '>' . $this->e((string) $template['name']) . '</option>';
            }
            $sectionRows .= '<tr><td>' . $this->e((string) $section['name']) . '</td><td><form method="post" action="/admin/social/section-template"><input type="hidden" name="csrf_token" value="' . $csrf . '"><input type="hidden" name="section_id" value="' . $sectionId . '"><select name="template_id">' . $options . '</select> <button type="submit">Save</button></form></td></tr>';
        }

        $historyRows = '';
        foreach ($this->social->history($tenant->tenantId) as $post) {
            $id = (int) $post['id'];
            $status = $this->e((string) $post['status']);
            $when = $this->e((string) ($post['published_at'] ?: $post['scheduled_at'] ?: $post['created_at']));
            $link = trim((string) ($post['remote_permalink'] ?? '')) !== '' ? '<a href="' . $this->e((string) $post['remote_permalink']) . '" target="_blank" rel="noopener">Instagram post</a>' : '';
            $actions = '';
            if (in_array((string) $post['status'], ['draft','scheduled','failed','authorization_required'], true)) {
                $actions = '<a href="/admin/social/compose?post_id=' . $id . '">Edit</a> <form method="post" action="/admin/social/cancel" style="display:inline"><input type="hidden" name="csrf_token" value="' . $csrf . '"><input type="hidden" name="post_id" value="' . $id . '"><button type="submit">Cancel</button></form>';
            }
            $error = trim((string) ($post['last_error'] ?? '')) !== '' ? '<br><small>' . $this->e((string) $post['last_error']) . '</small>' : '';
            $historyRows .= '<tr><td>' . $this->e((string) ($post['artwork_title'] ?? 'Artwork')) . '</td><td>' . $status . $error . '</td><td>' . $when . '</td><td>' . $link . '</td><td>' . $actions . '</td></tr>';
        }
        if ($historyRows === '') {
            $historyRows = '<tr><td colspan="5">No Instagram posts have been submitted yet.</td></tr>';
        }

        $placeholders = '{title}, {artist_name}, {year}, {medium}, {dimensions}, {year_medium_dimensions}, {description}, {social_caption}, {social_or_description}, {artwork_url}, {website}, {site_url}, {portfolio_url}, {portfolio_name}, {section_name}, {section_names}, {price}, {availability}, {copyright_year}, {copyright_holder}, {default_hashtags}, {artwork_hashtags}, {hashtags}';
        $body = '<p><a href="/admin">&larr; Tenant Admin</a></p><h1>Instagram</h1><section class="admin-card"><h2>Connection</h2>' . $connectionHtml . '<p>ArtsFolio requires an Instagram Creator or Business account. Passwords are never stored.</p></section>'
            . '<section class="admin-card"><h2>Publishing defaults</h2><form method="post" action="/admin/social/defaults"><input type="hidden" name="csrf_token" value="' . $csrf . '"><label>Default hashtags<br><textarea name="default_hashtags" rows="3">' . $defaultHashtags . '</textarea></label><p><button type="submit">Save defaults</button></p></form></section>'
            . '<section><h2>Caption templates</h2><p>Available placeholders: <code>' . $this->e($placeholders) . '</code>. Internal artwork notes are intentionally unavailable.</p><div class="tenant-admin-action-grid">' . $templateCards . '</div></section>'
            . '<section class="admin-card"><h2>Template by portfolio section</h2><p>If an artwork belongs to multiple sections with conflicting templates, Compose uses the tenant default and surfaces all templates for explicit selection.</p><table class="admin-table"><thead><tr><th>Section</th><th>Instagram template</th></tr></thead><tbody>' . $sectionRows . '</tbody></table></section>'
            . '<section class="admin-card"><h2>Scheduled &amp; history</h2><table class="admin-table"><thead><tr><th>Artwork</th><th>Status</th><th>Time</th><th>Instagram</th><th>Actions</th></tr></thead><tbody>' . $historyRows . '</tbody></table></section>';
        return Response::html($this->adminPage($tenant, 'Instagram', $body), 200, ['Cache-Control' => 'private, no-store']);
    }

    private function saveDefaults(TenantContext $tenant): Response
    {
        if (!$this->validCsrf()) return Response::invalidCsrf();
        $renderer = new SocialTemplateRenderer($this->settings);
        $this->settings->set($tenant, 'instagram_default_hashtags', $renderer->normalizeHashtags((string) ($_POST['default_hashtags'] ?? '')));
        return $this->redirect('/admin/social?notice=defaults-saved');
    }

    private function saveTemplate(TenantContext $tenant): Response
    {
        if (!$this->validCsrf()) return Response::invalidCsrf();
        $name = trim((string) ($_POST['name'] ?? ''));
        $body = trim((string) ($_POST['template_body'] ?? ''));
        if ($name === '' || $body === '') return Response::error(422, 'Template name and body are required.');
        $id = max(0, (int) ($_POST['template_id'] ?? 0));
        $this->social->saveTemplate($tenant->tenantId, $id > 0 ? $id : null, $name, $body, isset($_POST['is_default']));
        return $this->redirect('/admin/social?notice=template-saved');
    }

    private function deleteTemplate(TenantContext $tenant): Response
    {
        if (!$this->validCsrf()) return Response::invalidCsrf();
        $this->social->deleteTemplate($tenant->tenantId, max(0, (int) ($_POST['template_id'] ?? 0)));
        return $this->redirect('/admin/social?notice=template-deleted');
    }

    private function saveSectionTemplate(TenantContext $tenant): Response
    {
        if (!$this->validCsrf()) return Response::invalidCsrf();
        $sectionId = max(0, (int) ($_POST['section_id'] ?? 0));
        $templateId = max(0, (int) ($_POST['template_id'] ?? 0));
        if ($sectionId < 1) return Response::error(422, 'Portfolio section is required.');
        $this->social->setSectionTemplate($tenant->tenantId, $sectionId, $templateId > 0 ? $templateId : null);
        return $this->redirect('/admin/social?notice=section-template-saved');
    }

    private function connect(TenantContext $tenant, ?array $currentUser): Response
    {
        $userId = (int) ($currentUser['user_id'] ?? 0);
        $redirectUri = $this->redirectUri();
        $state = $this->oauthState($tenant->tenantId, $userId, $tenant->slug);
        return new Response('', 303, ['Location' => (new InstagramClient())->authorizationUrl($state, $redirectUri)]);
    }

    private function oauthCallback(Request $request): Response
    {
        try {
            $state = $this->decodeOauthState((string) ($_GET['state'] ?? ''));
            $code = trim((string) ($_GET['code'] ?? ''));
            if ($code === '') throw new \RuntimeException('Instagram authorization code is missing.');
            $tenantId = (int) $state['tenant_id'];
            $userId = (int) $state['user_id'];
            $result = (new InstagramClient())->exchangeCode($code, $this->redirectUri());
            $ciphertext = (new SocialTokenCipher())->encrypt($result['access_token']);
            $this->social->saveConnection($tenantId, 'instagram', $result['user_id'], $result['username'], $ciphertext, $result['expires_at'], 'instagram_business_basic,instagram_business_content_publish', $userId);
            return new Response('', 303, ['Location' => 'https://' . $state['tenant_slug'] . '.artsfol.io/admin/social?notice=instagram-connected']);
        } catch (Throwable $e) {
            return Response::error(400, 'Instagram connection failed: ' . $e->getMessage());
        }
    }

    private function disconnect(TenantContext $tenant): Response
    {
        if (!$this->validCsrf()) return Response::invalidCsrf();
        $this->social->disconnect($tenant->tenantId);
        return $this->redirect('/admin/social?notice=instagram-disconnected');
    }

    private function compose(TenantContext $tenant, ?array $currentUser): Response
    {
        $postId = max(0, (int) ($_GET['post_id'] ?? 0));
        $existing = $postId > 0 ? $this->social->post($tenant->tenantId, $postId) : null;
        $artworkId = $existing ? (int) $existing['source_artwork_id'] : max(0, (int) ($_GET['artwork_id'] ?? 0));
        $artwork = $this->social->artwork($tenant->tenantId, $artworkId);
        if (!$artwork || empty($artwork['media_id'])) return Response::error(404, 'Artwork with a primary image is required.');
        $this->social->ensureDefaultTemplate($tenant->tenantId);
        $templates = $this->social->templates($tenant->tenantId);
        $effective = $this->social->effectiveTemplate($tenant->tenantId, $artwork);
        $selectedTemplateId = $existing ? (int) $existing['template_id'] : (int) ($effective['id'] ?? 0);
        $renderer = new SocialTemplateRenderer($this->settings);
        $values = $renderer->values($tenant, $artwork, $tenant->hostname);
        $caption = $existing ? (string) $existing['caption'] : $renderer->render((string) ($effective['template_body'] ?? ''), $values);
        $hashtags = $existing ? (string) ($existing['hashtags'] ?? '') : $values['hashtags'];
        $csrf = $this->e($this->csrf->getOrCreate());
        $templateOptions = '';
        foreach ($templates as $template) {
            $selected = (int) $template['id'] === $selectedTemplateId ? ' selected' : '';
            $templateOptions .= '<option value="' . (int) $template['id'] . '" data-template="' . $this->e((string) $template['template_body']) . '"' . $selected . '>' . $this->e((string) $template['name']) . '</option>';
        }

        $existingItems = [];
        if ($existing) foreach ($this->social->postItems($tenant->tenantId, $postId) as $item) $existingItems[(int) $item['artwork_id']] = $item;
        $mediaCards = '';
        $rank = 0;
        foreach ($this->social->carouselCandidates($tenant->tenantId, $artworkId) as $candidate) {
            $candidateId = (int) $candidate['id'];
            $selected = $candidateId === $artworkId || isset($existingItems[$candidateId]);
            $order = isset($existingItems[$candidateId]) ? (int) $existingItems[$candidateId]['sort_order'] : ($candidateId === $artworkId ? 0 : ++$rank);
            $cropMode = isset($existingItems[$candidateId]) ? (string) $existingItems[$candidateId]['crop_mode'] : 'original';
            $checked = $selected ? ' checked' : '';
            $src = '/admin/media?uuid=' . rawurlencode((string) $candidate['media_uuid']) . '&variant=thumb';
            $mediaCards .= '<article class="social-media-card" data-social-media-card data-order="' . $order . '"><label><input type="checkbox" name="media_artwork_ids[]" value="' . $candidateId . '"' . $checked . '> <strong>' . $this->e((string) $candidate['title']) . '</strong></label><img src="' . $this->e($src) . '" alt=""><input type="hidden" name="media_order[' . $candidateId . ']" value="' . $order . '" data-social-order><label>Crop <select name="crop_mode[' . $candidateId . ']" data-social-crop><option value="original"' . ($cropMode === 'original' ? ' selected' : '') . '>Original</option><option value="square"' . ($cropMode === 'square' ? ' selected' : '') . '>Square</option><option value="portrait"' . ($cropMode === 'portrait' ? ' selected' : '') . '>Portrait 4:5</option><option value="landscape"' . ($cropMode === 'landscape' ? ' selected' : '') . '>Landscape 1.91:1</option></select></label><label>Horizontal focus <input type="range" name="crop_x[' . $candidateId . ']" min="0" max="1" step="0.01" value="0.5"></label><label>Vertical focus <input type="range" name="crop_y[' . $candidateId . ']" min="0" max="1" step="0.01" value="0.5"></label><div><button type="button" data-social-up>↑</button> <button type="button" data-social-down>↓</button></div></article>';
        }

        $scheduledLocal = '';
        if ($existing && !empty($existing['scheduled_at'])) {
            $utc = new DateTimeImmutable((string) $existing['scheduled_at'], new DateTimeZone('UTC'));
            $scheduledLocal = $utc->setTimezone(new DateTimeZone((string) ($GLOBALS['artsfolio_user_timezone'] ?? 'UTC')))->format('Y-m-d\TH:i');
        }
        $body = '<p><a href="/admin/social">&larr; Instagram settings &amp; history</a></p><h1>Instagram Compose</h1><p><strong>' . $this->e((string) $artwork['title']) . '</strong></p><form method="post" action="/admin/social/post" data-social-compose><input type="hidden" name="csrf_token" value="' . $csrf . '"><input type="hidden" name="artwork_id" value="' . $artworkId . '"><input type="hidden" name="post_id" value="' . $postId . '"><label>Template<br><select name="template_id" data-social-template>' . $templateOptions . '</select></label><label>Caption<br><textarea name="caption" rows="14" data-social-caption required>' . $this->e($caption) . '</textarea></label><p><span data-social-character-count>0</span> characters</p><label>Hashtags<br><textarea name="hashtags" rows="3">' . $this->e($hashtags) . '</textarea></label><h2>Carousel media</h2><p>Select up to 10 images. Images from the same portfolio section are listed first. Crop settings create a non-destructive JPEG publication derivative.</p><div class="social-media-grid" data-social-media-grid>' . $mediaCards . '</div><label>Schedule date/time (' . $this->e((string) ($GLOBALS['artsfolio_user_timezone'] ?? 'UTC')) . ')<br><input type="datetime-local" name="scheduled_local" value="' . $this->e($scheduledLocal) . '"></label><p><button type="submit" name="action" value="post_now" onclick="return confirm(\'Publish this post to Instagram now?\');">Post Now</button> <button type="submit" name="action" value="schedule">Schedule Post</button></p></form>';
        return Response::html($this->adminPage($tenant, 'Instagram Compose', $body), 200, ['Cache-Control' => 'private, no-store']);
    }

    private function submitPost(TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->validCsrf()) return Response::invalidCsrf();
        $artworkId = max(0, (int) ($_POST['artwork_id'] ?? 0));
        $artwork = $this->social->artwork($tenant->tenantId, $artworkId);
        if (!$artwork) return Response::error(404, 'Artwork not found.');
        $templateId = max(0, (int) ($_POST['template_id'] ?? 0));
        if (!$this->social->template($tenant->tenantId, $templateId)) return Response::error(422, 'Invalid Instagram template.');
        $caption = trim((string) ($_POST['caption'] ?? ''));
        $renderer = new SocialTemplateRenderer($this->settings);
        $hashtags = $renderer->normalizeHashtags((string) ($_POST['hashtags'] ?? ''));
        if ($caption === '') return Response::error(422, 'Instagram caption is required.');
        $mediaIds = array_values(array_unique(array_map('intval', (array) ($_POST['media_artwork_ids'] ?? []))));
        $mediaIds = array_values(array_filter($mediaIds, static fn (int $id): bool => $id > 0));
        if ($mediaIds === [] || count($mediaIds) > 10) return Response::error(422, 'Select between one and ten carousel images.');
        $ordered = [];
        foreach ($mediaIds as $id) $ordered[] = ['id' => $id, 'order' => (int) ($_POST['media_order'][$id] ?? 9999)];
        usort($ordered, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);
        $action = (string) ($_POST['action'] ?? 'schedule');
        $scheduledAt = $action === 'post_now' ? gmdate('Y-m-d H:i:s') : $this->scheduledUtc((string) ($_POST['scheduled_local'] ?? ''));
        if ($scheduledAt === null) return Response::error(422, 'Choose a valid future schedule date and time.');
        $status = 'scheduled';
        $userId = (int) ($currentUser['user_id'] ?? 0);
        $postId = max(0, (int) ($_POST['post_id'] ?? 0));
        if ($postId > 0) {
            $this->replacePendingPost($tenant->tenantId, $postId, $artworkId, $templateId, $caption, $hashtags, $scheduledAt, $userId, $ordered);
        } else {
            $postId = $this->social->createPost($tenant->tenantId, $artworkId, $templateId, $caption, $hashtags, $status, $scheduledAt, ['source_artwork_id' => $artworkId, 'caption' => $caption, 'hashtags' => $hashtags, 'scheduled_at' => $scheduledAt], $userId);
            $this->addPostItems($tenant->tenantId, $postId, $ordered);
        }
        $this->ensurePublisherJob($action === 'post_now' ? 0 : 60);
        return $this->redirect('/admin/social?notice=' . ($action === 'post_now' ? 'post-submitted' : 'post-scheduled'));
    }

    private function cancelPost(TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->validCsrf()) return Response::invalidCsrf();
        $this->social->cancelPost($tenant->tenantId, max(0, (int) ($_POST['post_id'] ?? 0)), (int) ($currentUser['user_id'] ?? 0));
        return $this->redirect('/admin/social?notice=post-cancelled');
    }

    private function saveArtworkMetadata(TenantContext $tenant): Response
    {
        if (!$this->validCsrf()) return Response::invalidCsrf();
        $artworkId = max(0, (int) ($_POST['artwork_id'] ?? 0));
        if (!$this->social->artwork($tenant->tenantId, $artworkId)) return Response::error(404, 'Artwork not found.');
        $renderer = new SocialTemplateRenderer($this->settings);
        $this->social->saveArtworkSocialMetadata($tenant->tenantId, $artworkId, trim((string) ($_POST['social_caption'] ?? '')), $renderer->normalizeHashtags((string) ($_POST['social_hashtags'] ?? '')));
        return Response::json(['ok' => true, 'message' => 'Social caption and hashtags saved.']);
    }

    private function media(Request $request, string $token): Response
    {
        $tenant = (new TenantResolver($this->pdo))->resolveFromHost($request->host());
        $row = $this->social->mediaToken($token);
        if (!$tenant || !$row || (int) $row['tenant_id'] !== $tenant->tenantId) return Response::notFound();
        $absolute = $this->root . '/' . ltrim((string) $row['storage_path'], '/');
        if (!is_file($absolute)) return Response::notFound();
        $bytes = file_get_contents($absolute);
        if ($bytes === false) return Response::notFound();
        return new Response($bytes, 200, ['Content-Type' => (string) $row['mime_type'], 'Content-Length' => (string) strlen($bytes), 'Cache-Control' => 'private, max-age=300, no-store', 'X-Robots-Tag' => 'noindex, nofollow, noarchive']);
    }

    private function replacePendingPost(int $tenantId, int $postId, int $artworkId, int $templateId, string $caption, string $hashtags, string $scheduledAt, int $userId, array $ordered): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("UPDATE social_posts SET source_artwork_id = :artwork_id, template_id = :template_id, caption = :caption, hashtags = :hashtags, snapshot_json = :snapshot_json, status = 'scheduled', scheduled_at = :scheduled_at, next_attempt_at = :scheduled_at, last_error = NULL, updated_by_user_id = :user_id, updated_at = CURRENT_TIMESTAMP WHERE tenant_id = :tenant_id AND id = :id AND status IN ('draft','scheduled','failed','authorization_required')");
            $stmt->execute(['artwork_id' => $artworkId, 'template_id' => $templateId, 'caption' => $caption, 'hashtags' => $hashtags, 'snapshot_json' => json_encode(['source_artwork_id' => $artworkId, 'caption' => $caption, 'hashtags' => $hashtags, 'scheduled_at' => $scheduledAt], JSON_THROW_ON_ERROR), 'scheduled_at' => $scheduledAt, 'user_id' => $userId, 'tenant_id' => $tenantId, 'id' => $postId]);
            if ($stmt->rowCount() !== 1) throw new \RuntimeException('This post can no longer be edited because publishing has already started.');
            $delete = $this->pdo->prepare('DELETE FROM social_post_items WHERE tenant_id = :tenant_id AND social_post_id = :post_id');
            $delete->execute(['tenant_id' => $tenantId, 'post_id' => $postId]);
            $this->addPostItems($tenantId, $postId, $ordered);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function addPostItems(int $tenantId, int $postId, array $ordered): void
    {
        foreach ($ordered as $index => $selected) {
            $candidate = $this->social->artwork($tenantId, (int) $selected['id']);
            if (!$candidate || empty($candidate['media_id'])) throw new \RuntimeException('One selected carousel image is no longer available.');
            $id = (int) $selected['id'];
            $cropMode = (string) ($_POST['crop_mode'][$id] ?? 'original');
            if (!in_array($cropMode, ['original','square','portrait','landscape'], true)) $cropMode = 'original';
            $this->social->addPostItem($postId, $tenantId, $id, (int) $candidate['media_id'], $index, $cropMode, ['x' => max(0.0, min(1.0, (float) ($_POST['crop_x'][$id] ?? 0.5))), 'y' => max(0.0, min(1.0, (float) ($_POST['crop_y'][$id] ?? 0.5)))]);
        }
    }

    private function ensurePublisherJob(int $delaySeconds): void
    {
        (new \App\Platform\Jobs\BackgroundJobRepository($this->pdo))->enqueueSingleton('social.publish_due', ['interval_seconds' => 60, 'batch_size' => 10], null, max(0, $delaySeconds));
    }

    private function scheduledUtc(string $local): ?string
    {
        $local = trim($local);
        if ($local === '') return null;
        try {
            $timezone = new DateTimeZone((string) ($GLOBALS['artsfolio_user_timezone'] ?? 'UTC'));
            $date = new DateTimeImmutable($local, $timezone);
            if ($date->getTimestamp() < time() - 30) return null;
            return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    private function oauthState(int $tenantId, int $userId, string $tenantSlug): string
    {
        $payload = base64_encode(json_encode(['tenant_id' => $tenantId, 'user_id' => $userId, 'tenant_slug' => $tenantSlug, 'expires' => time() + 900, 'nonce' => bin2hex(random_bytes(12))], JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $payload, $this->stateKey());
        return rtrim(strtr($payload, '+/', '-_'), '=') . '.' . $signature;
    }

    private function decodeOauthState(string $state): array
    {
        [$encoded, $signature] = array_pad(explode('.', $state, 2), 2, '');
        $payload = strtr($encoded, '-_', '+/');
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        if ($encoded === '' || $signature === '' || !hash_equals(hash_hmac('sha256', $payload, $this->stateKey()), $signature)) throw new \RuntimeException('Instagram OAuth state is invalid.');
        $decoded = json_decode((string) base64_decode($payload, true), true);
        if (!is_array($decoded) || (int) ($decoded['expires'] ?? 0) < time() || (int) ($decoded['tenant_id'] ?? 0) < 1 || (int) ($decoded['user_id'] ?? 0) < 1) throw new \RuntimeException('Instagram OAuth state is expired or incomplete.');
        return $decoded;
    }

    private function stateKey(): string
    {
        $configured = trim((string) (getenv('ARTSFOLIO_SOCIAL_STATE_KEY') ?: getenv('ARTSFOLIO_SOCIAL_TOKEN_KEY') ?: ''));
        if ($configured === '') throw new \RuntimeException('ARTSFOLIO_SOCIAL_STATE_KEY is not configured.');
        return $configured;
    }

    private function redirectUri(): string
    {
        return trim((string) (getenv('ARTSFOLIO_INSTAGRAM_REDIRECT_URI') ?: 'https://artsfol.io/social/instagram/callback'));
    }

    private function validCsrf(): bool
    {
        return $this->csrf->validate((string) ($_POST['csrf_token'] ?? ''));
    }

    private function redirect(string $path): Response
    {
        return new Response('', 303, ['Location' => $path]);
    }

    private function adminPage(TenantContext $tenant, string $title, string $body): string
    {
        $safeTitle = $this->e($title);
        $site = $this->e((string) $this->settings->get($tenant, 'site_title', $tenant->name));
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $safeTitle . ' | ' . $site . ' Admin</title><link rel="stylesheet" href="/assets/site.css"><link rel="stylesheet" href="/assets/tenant-admin.css"><script src="/assets/social-publishing.js" defer></script></head><body class="tenant-admin-page"><header class="site-header"><a class="brand" href="/admin">' . $site . ' Tenant Admin</a><nav><a href="/admin/artworks">Artworks</a><a href="/admin/social">Instagram</a><a href="/">View site</a></nav></header><main class="tenant-admin-main"><section class="tenant-admin-panel">' . $body . '</section></main></body></html>';
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// End of file.
