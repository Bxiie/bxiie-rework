<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;


use App\Http\View\ErrorPage;
use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use PDO;
use App\Http\View\AdminLayout;

final class EventsController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly PDO $pdo,
        private readonly CsrfTokenService $csrf,
    ) {
    }

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login'), 403);
        }

        $stmt = $this->pdo->prepare("SELECT * FROM exhibitions WHERE tenant_id = :tenant_id AND status <> 'archived' ORDER BY sort_order ASC, id DESC");
        $stmt->execute(['tenant_id' => $tenant->tenantId]);

        $rows = '';
        foreach ($stmt->fetchAll() as $event) {
            $id = (int) $event['id'];
            $name = htmlspecialchars((string) $event['name'], ENT_QUOTES, 'UTF-8');
            $date = htmlspecialchars((string) ($event['exhibition_date'] ?? ''), ENT_QUOTES, 'UTF-8');
            $type = htmlspecialchars((string) ($event['exhibition_type'] ?? ''), ENT_QUOTES, 'UTF-8');
            $location = htmlspecialchars((string) ($event['location'] ?? ''), ENT_QUOTES, 'UTF-8');
            $rows .= "<tr><td>{$date}</td><td>{$name}</td><td>{$type}</td><td>{$location}</td><td><a href=\"/admin/events/edit?id={$id}\">Edit</a></td></tr>\n";
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="5">No events yet.</td></tr>';
        }

        $body = <<<HTML
<main>
<p><a href="/admin">&larr; Admin</a></p>
<h1>Events / Exhibitions</h1>
<p><a href="/admin/events/edit">Add event</a></p>
<table border="1" cellpadding="8" cellspacing="0">
<tr><th>Date</th><th>Name</th><th>Type</th><th>Location</th><th></th></tr>
{$rows}
</table>
</main>
HTML;

        return Response::html(AdminLayout::render('Events', $body));
    }

    public function edit(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login'), 403);
        }

        $id = (int) ($_GET['id'] ?? 0);
        $event = $id > 0 ? $this->find($tenant, $id) : null;

        $token = htmlspecialchars($this->csrf->getOrCreate(), ENT_QUOTES, 'UTF-8');
        $v = fn (string $key): string => htmlspecialchars((string) ($event[$key] ?? ''), ENT_QUOTES, 'UTF-8');

        $body = <<<HTML
<main>
<p><a href="/admin/events">&larr; Events</a></p>
<h1>Edit event</h1>
<form method="post" action="/admin/events/edit">
<input type="hidden" name="csrf_token" value="{$token}">
<input type="hidden" name="id" value="{$id}">
<p><label>Date<br><input name="exhibition_date" value="{$v('exhibition_date')}" style="width:100%"></label></p>
<p><label>Name<br><input name="name" value="{$v('name')}" required style="width:100%"></label></p>
<p><label>Type<br><input name="exhibition_type" value="{$v('exhibition_type')}" style="width:100%"></label></p>
<p><label>Location<br><input name="location" value="{$v('location')}" style="width:100%"></label></p>
<p><label>City<br><input name="city" value="{$v('city')}" style="width:100%"></label></p>
<p><label>State/region<br><input name="state_region" value="{$v('state_region')}" style="width:100%"></label></p>
<p><label>Work name<br><input name="work_name" value="{$v('work_name')}" style="width:100%"></label></p>
<p><label>Notes<br><textarea name="notes" rows="8" style="width:100%">{$v('notes')}</textarea></label></p>
<button>Save event</button>
</form>
</main>
HTML;

        return Response::html(AdminLayout::render('Events', $body));
    }

    public function update(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login'), 403);
        }

        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::html('<h1>Invalid request</h1>', 419);
        }

        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));

        if ($name === '') {
            return Response::html('<h1>Name is required</h1>', 422);
        }

        $data = [
            'tenant_id' => $tenant->tenantId,
            'exhibition_date' => trim((string) ($_POST['exhibition_date'] ?? '')) ?: null,
            'name' => $name,
            'exhibition_type' => trim((string) ($_POST['exhibition_type'] ?? '')) ?: null,
            'location' => trim((string) ($_POST['location'] ?? '')) ?: null,
            'city' => trim((string) ($_POST['city'] ?? '')) ?: null,
            'state_region' => trim((string) ($_POST['state_region'] ?? '')) ?: null,
            'work_name' => trim((string) ($_POST['work_name'] ?? '')) ?: null,
            'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
        ];

        if ($id > 0 && $this->find($tenant, $id)) {
            $data['id'] = $id;
            $stmt = $this->pdo->prepare("UPDATE exhibitions SET exhibition_date=:exhibition_date, name=:name, exhibition_type=:exhibition_type, location=:location, city=:city, state_region=:state_region, work_name=:work_name, notes=:notes, updated_at=CURRENT_TIMESTAMP WHERE id=:id AND tenant_id=:tenant_id");
            $stmt->execute($data);
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO exhibitions (uuid, tenant_id, exhibition_date, name, exhibition_type, location, city, state_region, work_name, notes, status, created_at, updated_at) VALUES (UUID(), :tenant_id, :exhibition_date, :name, :exhibition_type, :location, :city, :state_region, :work_name, :notes, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $stmt->execute($data);
        }

        return new Response('', 303, ['Location' => '/admin/events']);
    }

    private function find(TenantContext $tenant, int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM exhibitions WHERE tenant_id = :tenant_id AND id = :id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}

// End of file.
