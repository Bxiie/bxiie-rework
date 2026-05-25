<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Http\View\ErrorPage;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use PDO;

/**
 * Tenant-admin event and exhibition management.
 */
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

        $query = trim((string) ($_GET['q'] ?? ''));
        $typeFilter = trim((string) ($_GET['type'] ?? ''));
        $statusFilter = (string) ($_GET['status'] ?? 'active');
        $sort = $this->safeSort((string) ($_GET['sort'] ?? 'sort_order'));
        $direction = strtolower((string) ($_GET['direction'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';

        $where = ['tenant_id = :tenant_id'];
        $params = ['tenant_id' => $tenant->tenantId];

        if ($statusFilter === 'archived') {
            $where[] = "status = 'archived'";
        } elseif ($statusFilter !== 'all') {
            $where[] = "status <> 'archived'";
            $statusFilter = 'active';
        }

        if ($query !== '') {
            $where[] = '(name LIKE :query OR location LIKE :query OR city LIKE :query OR state_region LIKE :query OR work_name LIKE :query OR notes LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($typeFilter !== '') {
            $where[] = 'exhibition_type = :type';
            $params['type'] = $typeFilter;
        }

        $sql = 'SELECT * FROM exhibitions WHERE ' . implode(' AND ', $where) . " ORDER BY {$sort} {$direction}, id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $typeOptions = $this->typeOptions($tenant, $typeFilter);
        $statusOptions = $this->statusOptions($statusFilter);
        $querySafe = htmlspecialchars($query, ENT_QUOTES, 'UTF-8');
        $sortOptions = $this->sortOptions($sort);
        $directionOptions = $this->directionOptions($direction);

        $rows = '';
        foreach ($stmt->fetchAll() as $event) {
            $id = (int) $event['id'];
            $date = $this->e((string) ($event['exhibition_date'] ?? ''));
            $name = $this->e((string) $event['name']);
            $type = $this->e((string) ($event['exhibition_type'] ?? ''));
            $location = $this->e((string) ($event['location'] ?? ''));
            $sortOrder = $this->e((string) ($event['sort_order'] ?? ''));
            $status = $this->e((string) ($event['status'] ?? 'active'));
            $rows .= "<tr><td>{$sortOrder}</td><td>{$date}</td><td>{$name}</td><td>{$type}</td><td>{$location}</td><td>{$status}</td><td><a href=\"/admin/events/edit?id={$id}\">Edit</a></td></tr>\n";
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="7">No events match the current filters.</td></tr>';
        }

        $body = <<<HTML
<main>
<p><a href="/admin">&larr; Admin</a></p>
<h1>Events / Exhibitions</h1>
<form method="get" action="/admin/events" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:end;margin:16px 0;">
    <label>Search<br><input type="search" name="q" value="{$querySafe}" placeholder="Name, work, location, notes"></label>
    <label>Type<br><select name="type">{$typeOptions}</select></label>
    <label>Status<br><select name="status">{$statusOptions}</select></label>
    <label>Sort by<br><select name="sort">{$sortOptions}</select></label>
    <label>Direction<br><select name="direction">{$directionOptions}</select></label>
    <button type="submit">Apply filters</button>
    <a href="/admin/events">Reset</a>
</form>
<p><a href="/admin/events/edit">Add event</a></p>
<table border="1" cellpadding="8" cellspacing="0">
<tr><th>Order</th><th>Date</th><th>Name</th><th>Type</th><th>Location</th><th>Status</th><th></th></tr>
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
        $v = fn (string $key): string => $this->e((string) ($event[$key] ?? ''));
        $statusOptions = $this->statusOptions((string) ($event['status'] ?? 'active'));

        $body = <<<HTML
<main>
<p><a href="/admin/events">&larr; Events</a></p>
<h1>Edit event</h1>
<form method="post" action="/admin/events/edit">
<input type="hidden" name="csrf_token" value="{$token}">
<input type="hidden" name="id" value="{$id}">
<p><label>Display/order number<br><input type="number" name="sort_order" value="{$v('sort_order')}" style="width:100%"></label></p>
<p><label>Status<br><select name="status" style="width:100%">{$statusOptions}</select></label></p>
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
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            'status' => in_array((string) ($_POST['status'] ?? 'active'), ['active', 'archived'], true) ? (string) $_POST['status'] : 'active',
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
            $stmt = $this->pdo->prepare("UPDATE exhibitions SET sort_order=:sort_order, status=:status, exhibition_date=:exhibition_date, name=:name, exhibition_type=:exhibition_type, location=:location, city=:city, state_region=:state_region, work_name=:work_name, notes=:notes, updated_at=CURRENT_TIMESTAMP WHERE id=:id AND tenant_id=:tenant_id");
            $stmt->execute($data);
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO exhibitions (uuid, tenant_id, sort_order, exhibition_date, name, exhibition_type, location, city, state_region, work_name, notes, status, created_at, updated_at) VALUES (UUID(), :tenant_id, :sort_order, :exhibition_date, :name, :exhibition_type, :location, :city, :state_region, :work_name, :notes, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
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

    private function safeSort(string $sort): string
    {
        return match ($sort) {
            'date' => 'exhibition_date',
            'name' => 'name',
            'type' => 'exhibition_type',
            'location' => 'location',
            'status' => 'status',
            default => 'sort_order',
        };
    }

    private function sortOptions(string $current): string
    {
        $options = [
            'sort_order' => 'Display/order number',
            'date' => 'Date',
            'name' => 'Name',
            'type' => 'Type',
            'location' => 'Location',
            'status' => 'Status',
        ];

        return $this->options($options, $current);
    }

    private function directionOptions(string $current): string
    {
        return $this->options(['ASC' => 'Ascending', 'DESC' => 'Descending'], $current);
    }

    private function statusOptions(string $current): string
    {
        return $this->options(['active' => 'Active', 'archived' => 'Archived', 'all' => 'All'], $current);
    }

    private function typeOptions(TenantContext $tenant, string $current): string
    {
        $stmt = $this->pdo->prepare("SELECT DISTINCT exhibition_type FROM exhibitions WHERE tenant_id = :tenant_id AND exhibition_type IS NOT NULL AND exhibition_type <> '' ORDER BY exhibition_type");
        $stmt->execute(['tenant_id' => $tenant->tenantId]);
        $options = ['' => 'All types'];

        foreach ($stmt->fetchAll() as $row) {
            $type = (string) $row['exhibition_type'];
            $options[$type] = $type;
        }

        return $this->options($options, $current);
    }

    private function options(array $options, string $current): string
    {
        $html = '';

        foreach ($options as $value => $label) {
            $selected = (string) $value === $current ? ' selected' : '';
            $html .= '<option value="' . $this->e((string) $value) . '"' . $selected . '>' . $this->e((string) $label) . '</option>';
        }

        return $html;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
