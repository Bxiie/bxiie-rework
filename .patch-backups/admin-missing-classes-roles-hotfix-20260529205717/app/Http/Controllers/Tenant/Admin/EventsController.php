<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\ErrorPage;
use App\Http\View\TenantAdminLayout;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Settings\TenantSettingsRepository;
use PDO;

/**
 * Tenant event/exhibition administration with filtering and ordering controls.
 */
final class EventsController
{
    public function __construct(private readonly RequireTenantRoleBrowser $roles, private readonly PDO $pdo, private readonly CsrfTokenService $csrf) {}

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login'), 403);
        }

        $status = (string) ($_GET['status'] ?? 'active');
        $q = trim((string) ($_GET['q'] ?? ''));
        $sort = (string) ($_GET['sort'] ?? 'sort_order');
        $allowedSorts = ['sort_order' => 'sort_order ASC, id DESC', 'date_desc' => 'exhibition_date DESC, id DESC', 'date_asc' => 'exhibition_date ASC, id ASC', 'name' => 'name ASC'];
        $orderSql = $allowedSorts[$sort] ?? $allowedSorts['sort_order'];

        $where = ['tenant_id = :tenant_id'];
        $params = ['tenant_id' => $tenant->tenantId];
        if ($status !== 'all') { $where[] = 'status = :status'; $params['status'] = $status; }
        if ($q !== '') { $where[] = '(name LIKE :q OR exhibition_type LIKE :q OR location LIKE :q OR city LIKE :q OR notes LIKE :q)'; $params['q'] = '%' . $q . '%'; }

        $stmt = $this->pdo->prepare('SELECT * FROM exhibitions WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orderSql);
        $stmt->execute($params);

        $token = htmlspecialchars($this->csrf->getOrCreate(), ENT_QUOTES, 'UTF-8');
        $rows = '';
        foreach ($stmt->fetchAll() as $event) {
            $id = (int) $event['id'];
            $rows .= '<tr>'
                . '<td><input form="event-order-form" type="number" name="sort_order[' . $id . ']" value="' . self::e((string) ($event['sort_order'] ?? '0')) . '" style="width:5rem"></td>'
                . '<td>' . self::e((string) ($event['exhibition_date'] ?? '')) . '</td>'
                . '<td>' . self::e((string) $event['name']) . '</td>'
                . '<td>' . self::e((string) ($event['exhibition_type'] ?? '')) . '</td>'
                . '<td>' . self::e((string) ($event['location'] ?? '')) . '</td>'
                . '<td>' . self::e((string) ($event['status'] ?? '')) . '</td>'
                . '<td><a href="/admin/events/edit?id=' . $id . '">Edit</a></td></tr>';
        }
        if ($rows === '') { $rows = '<tr><td colspan="7">No matching events.</td></tr>'; }

        $body = <<<HTML
<form class="admin-form" method="get" action="/admin/events">
<label>Search <input name="q" value="{$this->e($q)}"></label>
<label>Status <select name="status"><option value="active">Active</option><option value="archived">Archived</option><option value="all">All</option></select></label>
<label>Sort <select name="sort"><option value="sort_order">Manual order</option><option value="date_desc">Date newest first</option><option value="date_asc">Date oldest first</option><option value="name">Name</option></select></label>
<button type="submit">Filter</button> <a class="admin-button" href="/admin/events/edit">Add event</a>
</form>
<form id="event-order-form" method="post" action="/admin/events/order"><input type="hidden" name="csrf_token" value="{$token}"></form>
<table class="admin-table"><thead><tr><th>Order</th><th>Date</th><th>Name</th><th>Type</th><th>Location</th><th>Status</th><th></th></tr></thead><tbody>{$rows}</tbody></table>
<p><button form="event-order-form" type="submit">Save manual order</button></p>
HTML;
        return Response::html((new TenantAdminLayout(new TenantSettingsRepository($this->pdo)))->render($tenant, 'Events', $body, 'events'));
    }

    public function order(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) { return Response::html(ErrorPage::unauthorized('/login'), 403); }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) { return Response::html('<h1>Invalid request</h1>', 419); }
        $stmt = $this->pdo->prepare('UPDATE exhibitions SET sort_order = :sort_order, updated_at = CURRENT_TIMESTAMP WHERE tenant_id = :tenant_id AND id = :id');
        foreach ((array) ($_POST['sort_order'] ?? []) as $id => $order) { $stmt->execute(['tenant_id' => $tenant->tenantId, 'id' => (int) $id, 'sort_order' => (int) $order]); }
        return new Response('', 303, ['Location' => '/admin/events']);
    }

    public function edit(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) { return Response::html(ErrorPage::unauthorized('/login'), 403); }
        $id = (int) ($_GET['id'] ?? 0); $event = $id > 0 ? $this->find($tenant, $id) : null; $token = self::e($this->csrf->getOrCreate()); $v = fn (string $key): string => self::e((string) ($event[$key] ?? ''));
        $body = <<<HTML
<p><a href="/admin/events">&larr; Events</a></p><form class="admin-form" method="post" action="/admin/events/edit"><input type="hidden" name="csrf_token" value="{$token}"><input type="hidden" name="id" value="{$id}"><label>Date<input name="exhibition_date" value="{$v('exhibition_date')}"></label><label>Name<input name="name" value="{$v('name')}" required></label><label>Type<input name="exhibition_type" value="{$v('exhibition_type')}"></label><label>Location<input name="location" value="{$v('location')}"></label><label>City<input name="city" value="{$v('city')}"></label><label>State/region<input name="state_region" value="{$v('state_region')}"></label><label>Work name<input name="work_name" value="{$v('work_name')}"></label><label>Status<select name="status"><option value="active">Active</option><option value="archived">Archived</option></select></label><label>Sort order<input type="number" name="sort_order" value="{$v('sort_order')}"></label><label>Notes<textarea name="notes" rows="8">{$v('notes')}</textarea></label><button>Save event</button></form>
HTML;
        return Response::html((new TenantAdminLayout(new TenantSettingsRepository($this->pdo)))->render($tenant, 'Edit Event', $body, 'events'));
    }

    public function update(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) { return Response::html(ErrorPage::unauthorized('/login'), 403); }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) { return Response::html('<h1>Invalid request</h1>', 419); }
        $id = (int) ($_POST['id'] ?? 0); $name = trim((string) ($_POST['name'] ?? '')); if ($name === '') { return Response::html('<h1>Name is required</h1>', 422); }
        $data = ['tenant_id' => $tenant->tenantId, 'exhibition_date' => trim((string) ($_POST['exhibition_date'] ?? '')) ?: null, 'name' => $name, 'exhibition_type' => trim((string) ($_POST['exhibition_type'] ?? '')) ?: null, 'location' => trim((string) ($_POST['location'] ?? '')) ?: null, 'city' => trim((string) ($_POST['city'] ?? '')) ?: null, 'state_region' => trim((string) ($_POST['state_region'] ?? '')) ?: null, 'work_name' => trim((string) ($_POST['work_name'] ?? '')) ?: null, 'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null, 'status' => in_array((string) ($_POST['status'] ?? 'active'), ['active', 'archived'], true) ? (string) $_POST['status'] : 'active', 'sort_order' => (int) ($_POST['sort_order'] ?? 0)];
        if ($id > 0 && $this->find($tenant, $id)) { $data['id'] = $id; $this->pdo->prepare('UPDATE exhibitions SET exhibition_date=:exhibition_date, name=:name, exhibition_type=:exhibition_type, location=:location, city=:city, state_region=:state_region, work_name=:work_name, notes=:notes, status=:status, sort_order=:sort_order, updated_at=CURRENT_TIMESTAMP WHERE id=:id AND tenant_id=:tenant_id')->execute($data); }
        else { $this->pdo->prepare("INSERT INTO exhibitions (uuid, tenant_id, exhibition_date, name, exhibition_type, location, city, state_region, work_name, notes, status, sort_order, created_at, updated_at) VALUES (UUID(), :tenant_id, :exhibition_date, :name, :exhibition_type, :location, :city, :state_region, :work_name, :notes, :status, :sort_order, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)")->execute($data); }
        return new Response('', 303, ['Location' => '/admin/events']);
    }

    private function find(TenantContext $tenant, int $id): ?array { $stmt = $this->pdo->prepare('SELECT * FROM exhibitions WHERE tenant_id = :tenant_id AND id = :id LIMIT 1'); $stmt->execute(['tenant_id' => $tenant->tenantId, 'id' => $id]); $row = $stmt->fetch(); return $row ?: null; }
    private static function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
}

// End of file.
