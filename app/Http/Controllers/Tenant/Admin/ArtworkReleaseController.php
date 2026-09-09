<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Jobs\BackgroundJobRepository;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class ArtworkReleaseController
{
    public function __construct(private readonly RequireTenantRoleBrowser $roles, private readonly PDO $pdo, private readonly CsrfTokenService $csrf)
    {
    }

    public function index(Request $request, TenantContext $tenant, ?array $user): Response
    {
        if (!$this->allowed($user, $tenant)) return Response::error(403, 'Tenant admin access required.');
        $token = AdminLayout::escape($this->csrf->getOrCreate());
        $groups = $this->pdo->prepare("SELECT g.*, COUNT(i.artwork_id) item_count FROM artwork_release_groups g LEFT JOIN artwork_release_group_items i ON i.release_group_id = g.id WHERE g.tenant_id = :tenant_id GROUP BY g.id ORDER BY COALESCE(g.scheduled_at, g.created_at) DESC");
        $groups->execute(['tenant_id' => $tenant->tenantId]);
        $rows = '';
        foreach ($groups->fetchAll(PDO::FETCH_ASSOC) ?: [] as $group) {
            $id = (int) $group['id'];
            $rows .= '<tr><td><a href="/admin/release-groups/edit?id=' . $id . '">' . AdminLayout::escape((string) $group['name']) . '</a></td><td>' . (int) $group['item_count'] . '</td><td>' . AdminLayout::escape((string) $group['status']) . '</td><td>' . AdminLayout::escape((string) ($group['scheduled_at'] ?? '')) . '</td></tr>';
        }
        if ($rows === '') $rows = '<tr><td colspan="4">No release groups yet.</td></tr>';
        $artworks = $this->pdo->prepare("SELECT id, title, scheduled_publish_at FROM artworks WHERE tenant_id = :tenant_id AND status = 'draft' ORDER BY LOWER(title), id");
        $artworks->execute(['tenant_id' => $tenant->tenantId]);
        $options = '';
        foreach ($artworks->fetchAll(PDO::FETCH_ASSOC) ?: [] as $artwork) {
            $label = (string) $artwork['title'] . (!empty($artwork['scheduled_publish_at']) ? ' — scheduled ' . $artwork['scheduled_publish_at'] . ' UTC' : '');
            $options .= '<option value="' . (int) $artwork['id'] . '">' . AdminLayout::escape($label) . '</option>';
        }
        $notice = isset($_GET['saved']) ? '<p class="admin-notice admin-notice-success">Release group saved.</p>' : '';
        $body = $notice . '<p>Release groups publish all assigned draft artworks to the website together.</p><form method="post" action="/admin/release-groups/create"><input type="hidden" name="csrf_token" value="' . $token . '"><label>New release group name<br><input name="name" required></label><button type="submit">Create release group</button></form><table class="admin-table"><thead><tr><th>Group</th><th>Images</th><th>Status</th><th>Scheduled</th></tr></thead><tbody>' . $rows . '</tbody></table><h2>Schedule one artwork</h2><form method="post" action="/admin/artworks/schedule"><input type="hidden" name="csrf_token" value="' . $token . '"><label>Draft artwork<br><select name="artwork_id" required><option value="">Select artwork</option>' . $options . '</select></label><label>Publish date/time (' . AdminLayout::escape((string) ($GLOBALS['artsfolio_user_timezone'] ?? 'UTC')) . ')<br><input type="datetime-local" name="scheduled_local"></label><button type="submit">Save publication schedule</button></form>';
        return Response::html(AdminLayout::render('Release Groups', $body, 'artworks'));
    }

    public function create(Request $request, TenantContext $tenant, ?array $user): Response
    {
        if (!$this->allowed($user, $tenant)) return Response::error(403, 'Tenant admin access required.');
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) return Response::invalidCsrf();
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') return Response::error(422, 'Release group name is required.');
        $stmt = $this->pdo->prepare("INSERT INTO artwork_release_groups (tenant_id, name, created_by_user_id, created_at, updated_at) VALUES (:tenant_id, :name, :user_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'name' => $name, 'user_id' => (int) ($user['user_id'] ?? 0) ?: null]);
        return new Response('', 303, ['Location' => '/admin/release-groups/edit?id=' . (int) $this->pdo->lastInsertId()]);
    }

    public function edit(Request $request, TenantContext $tenant, ?array $user): Response
    {
        if (!$this->allowed($user, $tenant)) return Response::error(403, 'Tenant admin access required.');
        $id = max(0, (int) ($_GET['id'] ?? 0));
        $group = $this->group($tenant->tenantId, $id);
        if (!$group) return Response::notFound();
        $token = AdminLayout::escape($this->csrf->getOrCreate());
        $artworks = $this->pdo->prepare("SELECT a.id, a.title, a.status, i.artwork_id assigned FROM artworks a LEFT JOIN artwork_release_group_items i ON i.artwork_id = a.id AND i.tenant_id = a.tenant_id AND i.release_group_id = :group_id WHERE a.tenant_id = :tenant_id AND a.status <> 'archived' ORDER BY LOWER(a.title), a.id");
        $artworks->execute(['group_id' => $id, 'tenant_id' => $tenant->tenantId]);
        $choices = '';
        foreach ($artworks->fetchAll(PDO::FETCH_ASSOC) ?: [] as $artwork) {
            $aid = (int) $artwork['id'];
            $checked = $artwork['assigned'] !== null ? ' checked' : '';
            $choices .= '<label><input type="checkbox" name="artwork_ids[]" value="' . $aid . '"' . $checked . '> ' . AdminLayout::escape((string) $artwork['title']) . ' <small>(' . AdminLayout::escape((string) $artwork['status']) . ')</small></label>';
        }
        $local = '';
        if (!empty($group['scheduled_at'])) {
            $local = (new DateTimeImmutable((string) $group['scheduled_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone((string) ($GLOBALS['artsfolio_user_timezone'] ?? 'UTC')))->format('Y-m-d\TH:i');
        }
        $body = '<p><a href="/admin/release-groups">&larr; Release Groups</a></p><form method="post" action="/admin/release-groups/save"><input type="hidden" name="csrf_token" value="' . $token . '"><input type="hidden" name="id" value="' . $id . '"><label>Name<br><input name="name" value="' . AdminLayout::escape((string) $group['name']) . '" required></label><label>Schedule website release (' . AdminLayout::escape((string) ($GLOBALS['artsfolio_user_timezone'] ?? 'UTC')) . ')<br><input type="datetime-local" name="scheduled_local" value="' . AdminLayout::escape($local) . '"></label><fieldset><legend>Images</legend><div class="admin-checkbox-grid">' . $choices . '</div></fieldset><button type="submit">Save release group</button></form>';
        return Response::html(AdminLayout::render('Edit Release Group', $body, 'artworks'));
    }

    public function save(Request $request, TenantContext $tenant, ?array $user): Response
    {
        if (!$this->allowed($user, $tenant)) return Response::error(403, 'Tenant admin access required.');
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) return Response::invalidCsrf();
        $id = max(0, (int) ($_POST['id'] ?? 0));
        if (!$this->group($tenant->tenantId, $id)) return Response::notFound();
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') return Response::error(422, 'Release group name is required.');
        try {
            $scheduled = $this->utc((string) ($_POST['scheduled_local'] ?? ''));
        } catch (\InvalidArgumentException $e) {
            return Response::error(422, $e->getMessage());
        }
        $status = $scheduled !== null ? 'scheduled' : 'draft';
        $this->pdo->beginTransaction();
        try {
            $update = $this->pdo->prepare("UPDATE artwork_release_groups SET name = :name, status = :status, scheduled_at = :scheduled_at, deployed_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND tenant_id = :tenant_id");
            $update->execute(['name' => $name, 'status' => $status, 'scheduled_at' => $scheduled, 'id' => $id, 'tenant_id' => $tenant->tenantId]);
            $delete = $this->pdo->prepare('DELETE FROM artwork_release_group_items WHERE release_group_id = :id AND tenant_id = :tenant_id');
            $delete->execute(['id' => $id, 'tenant_id' => $tenant->tenantId]);
            $insert = $this->pdo->prepare('INSERT INTO artwork_release_group_items (release_group_id, tenant_id, artwork_id) SELECT :group_id, :tenant_id, id FROM artworks WHERE id = :artwork_id AND tenant_id = :tenant_id AND status <> "archived" ON DUPLICATE KEY UPDATE release_group_id = VALUES(release_group_id)');
            $clearIndividualSchedule = $this->pdo->prepare('UPDATE artworks SET scheduled_publish_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = :artwork_id AND tenant_id = :tenant_id');
            foreach (array_unique(array_map('intval', (array) ($_POST['artwork_ids'] ?? []))) as $artworkId) {
                if ($artworkId > 0) {
                    $insert->execute(['group_id' => $id, 'tenant_id' => $tenant->tenantId, 'artwork_id' => $artworkId]);
                    $clearIndividualSchedule->execute(['artwork_id' => $artworkId, 'tenant_id' => $tenant->tenantId]);
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
        $this->queue();
        return new Response('', 303, ['Location' => '/admin/release-groups?saved=1']);
    }

    public function scheduleArtwork(Request $request, TenantContext $tenant, ?array $user): Response
    {
        if (!$this->allowed($user, $tenant)) return Response::error(403, 'Tenant admin access required.');
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) return Response::invalidCsrf();
        $id = max(0, (int) ($_POST['artwork_id'] ?? 0));
        if ($id < 1) return Response::error(422, 'Select an artwork.');
        try {
            $scheduled = $this->utc((string) ($_POST['scheduled_local'] ?? ''));
        } catch (\InvalidArgumentException $e) {
            return Response::error(422, $e->getMessage());
        }
        $stmt = $this->pdo->prepare("UPDATE artworks SET status = 'draft', scheduled_publish_at = :scheduled_at, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND tenant_id = :tenant_id AND status <> 'archived'");
        $stmt->execute(['scheduled_at' => $scheduled, 'id' => $id, 'tenant_id' => $tenant->tenantId]);
        $this->queue();
        return new Response('', 303, ['Location' => '/admin/artworks']);
    }

    private function group(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM artwork_release_groups WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function utc(string $value): ?string
    {
        if (trim($value) === '') return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', trim($value), new DateTimeZone((string) ($GLOBALS['artsfolio_user_timezone'] ?? 'UTC')));
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new \InvalidArgumentException('Enter a valid publication date and time.');
        }
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function queue(): void
    {
        (new BackgroundJobRepository($this->pdo))->enqueueSingleton('artwork.publish_due', ['interval_seconds' => 60], null, 0);
    }

    private function allowed(?array $user, TenantContext $tenant): bool
    {
        return $this->roles->allows($user, $tenant, ['tenant_owner','tenant_admin','owner','admin']);
    }
}
