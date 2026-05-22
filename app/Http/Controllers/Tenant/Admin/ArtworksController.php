<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Tenancy\TenantContext;
use App\Platform\Audit\AuditLogRepository;
use PDO;

final class ArtworksController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly PDO $pdo,
        private readonly AuditLogRepository $auditLog,
    ) {
    }

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $stmt = $this->pdo->prepare(
            "SELECT
                a.id,
                a.title,
                a.slug,
                a.description,
                a.medium,
                a.year_created,
                a.status,
                a.sale_status,
                a.price,
                a.created_at,
                m.id AS media_id,
                m.uuid AS media_uuid,
                m.storage_path,
                m.mime_type,
                m.width,
                m.height
             FROM artworks a
             LEFT JOIN media_assets m ON m.id = a.primary_media_id
             WHERE a.tenant_id = :tenant_id
               AND a.status <> 'archived'
             ORDER BY a.id DESC
             LIMIT 200"
        );

        $stmt->execute(['tenant_id' => $tenant->tenantId]);
        $rows = $stmt->fetchAll();

        $items = '';

        foreach ($rows as $row) {
            $title = htmlspecialchars((string) $row['title'], ENT_QUOTES, 'UTF-8');
            $status = htmlspecialchars((string) $row['status'], ENT_QUOTES, 'UTF-8');
            $saleStatus = htmlspecialchars((string) $row['sale_status'], ENT_QUOTES, 'UTF-8');
            $price = htmlspecialchars((string) ($row['price'] ?? ''), ENT_QUOTES, 'UTF-8');
            $medium = htmlspecialchars((string) ($row['medium'] ?? ''), ENT_QUOTES, 'UTF-8');
            $year = htmlspecialchars((string) ($row['year_created'] ?? ''), ENT_QUOTES, 'UTF-8');
            $notes = htmlspecialchars((string) ($row['description'] ?? ''), ENT_QUOTES, 'UTF-8');
            $created = htmlspecialchars((string) $row['created_at'], ENT_QUOTES, 'UTF-8');
            $image = '';

            if (!empty($row['storage_path'])) {
                $src = '/admin/media?uuid=' . rawurlencode((string) $row['media_uuid']);
                $src = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
                $image = "<img src=\"{$src}\" alt=\"{$title}\" style=\"max-width:180px;max-height:140px;object-fit:contain;border:1px solid #ddd;background:#fff;\">";
            }

            $items .= <<<HTML
<tr id="artwork-{$row['id']}">
    <td>{$image}</td>
    <td>
        <strong>{$title}</strong><br>
        <small>ID {$row['id']} · {$created}</small>
    </td>
    <td>{$year}</td>
    <td>{$medium}</td>
    <td>{$status}</td>
    <td>{$saleStatus}</td>
    <td>{$price}</td>
    <td>{$notes}</td>
    <td>
        <a href="/admin/artworks/edit?id={$row['id']}">Edit</a>
        <form method="post" action="/admin/artworks/status" style="display:inline">
            <input type="hidden" name="id" value="{$row['id']}">
            <input type="hidden" name="status" value="published">
            <button type="submit" onclick="return confirm('Publish this artwork? It will become visible on public pages.');">Publish</button>
        </form>
        <form method="post" action="/admin/artworks/status" style="display:inline">
            <input type="hidden" name="id" value="{$row['id']}">
            <input type="hidden" name="status" value="draft">
            <button type="submit" onclick="return confirm('Unpublish this artwork? It will be hidden from public pages.');">Unpublish</button>
        </form>
        <form method="post" action="/admin/artworks/delete" style="display:inline" onsubmit="return confirm('Archive this artwork? It will disappear from normal review and public pages, but the file will not be deleted yet.');">
            <input type="hidden" name="id" value="{$row['id']}">
            <button type="submit">Archive</button>
        </form>
    </td>
</tr>
HTML;
        }

        if ($items === '') {
            $items = '<tr><td colspan="9">No artwork uploaded yet.</td></tr>';
        }

        $notice = match ((string) ($_GET['notice'] ?? '')) {
            'status-updated' => '<p style="padding:.75rem;background:#eef8ee;border:1px solid #9ac99a;">Artwork status updated.</p>',
            'artwork-archived' => '<p style="padding:.75rem;background:#fff4df;border:1px solid #d9b36a;">Artwork archived.</p>',
            default => '',
        };

        return Response::html(<<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Artworks</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<main>
    <p><a href="/admin">&larr; Admin</a></p>
    <h1>Artworks</h1>
    {$notice}
    <p><a href="/admin/artwork/upload">Upload artwork</a></p>
    <table border="1" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <th>Image</th>
                <th>Title</th>
                <th>Date/year</th>
                <th>Medium</th>
                <th>Status</th>
                <th>Sale</th>
                <th>Price</th>
                <th>Notes</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            {$items}
        </tbody>
    </table>
</main>
</body>
</html>
HTML);
    }

    public function edit(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $id = (int) ($_GET['id'] ?? 0);
        $artwork = $this->findArtwork($tenant, $id);

        if (!$artwork) {
            return Response::html('<h1>404</h1><p>Artwork not found.</p>', 404);
        }

        $title = htmlspecialchars((string) $artwork['title'], ENT_QUOTES, 'UTF-8');
        $year = htmlspecialchars((string) ($artwork['year_created'] ?? ''), ENT_QUOTES, 'UTF-8');
        $medium = htmlspecialchars((string) ($artwork['medium'] ?? ''), ENT_QUOTES, 'UTF-8');
        $notes = htmlspecialchars((string) ($artwork['description'] ?? ''), ENT_QUOTES, 'UTF-8');
        $price = htmlspecialchars((string) ($artwork['price'] ?? ''), ENT_QUOTES, 'UTF-8');
        $status = (string) $artwork['status'];
        $saleStatus = (string) $artwork['sale_status'];

        $selected = fn (string $a, string $b): string => $a === $b ? ' selected' : '';

        return Response::html(<<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Edit artwork</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<main>
    <p><a href="/admin/artworks">&larr; Artworks</a></p>
    <h1>Edit artwork</h1>
    <form method="post" action="/admin/artworks/edit">
        <input type="hidden" name="id" value="{$id}">
        <p><label>Title<br><input type="text" name="title" value="{$title}" required></label></p>
        <p><label>Date / year<br><input type="text" name="year_created" value="{$year}"></label></p>
        <p><label>Medium<br><input type="text" name="medium" value="{$medium}"></label></p>
        <p><label>Notes<br><textarea name="description" rows="6">{$notes}</textarea></label></p>
        <p>
            <label>Status<br>
                <select name="status">
                    <option value="draft"{$selected($status, 'draft')}>Draft</option>
                    <option value="published"{$selected($status, 'published')}>Published</option>
                    <option value="archived"{$selected($status, 'archived')}>Archived</option>
                </select>
            </label>
        </p>
        <p>
            <label>Sale status<br>
                <select name="sale_status">
                    <option value="nfs"{$selected($saleStatus, 'nfs')}>NFS</option>
                    <option value="for_sale"{$selected($saleStatus, 'for_sale')}>For sale</option>
                    <option value="sold"{$selected($saleStatus, 'sold')}>Sold</option>
                </select>
            </label>
        </p>
        <p><label>Price<br><input type="text" name="price" value="{$price}"></label></p>
        <button type="submit">Save artwork</button>
    </form>
</main>
</body>
</html>
HTML);
    }

    public function update(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $id = (int) ($_POST['id'] ?? 0);
        $artwork = $this->findArtwork($tenant, $id);

        if (!$artwork) {
            return Response::html('<h1>404</h1><p>Artwork not found.</p>', 404);
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            return Response::html('<h1>Invalid artwork</h1><p>Title is required.</p>', 422);
        }

        $status = in_array(($_POST['status'] ?? 'draft'), ['draft', 'published', 'archived'], true) ? (string) $_POST['status'] : 'draft';
        $saleStatus = in_array(($_POST['sale_status'] ?? 'nfs'), ['nfs', 'for_sale', 'sold'], true) ? (string) $_POST['sale_status'] : 'nfs';
        $price = $saleStatus === 'nfs' ? null : trim((string) ($_POST['price'] ?? ''));

        $stmt = $this->pdo->prepare(
            "UPDATE artworks
             SET title = :title,
                 year_created = :year_created,
                 medium = :medium,
                 description = :description,
                 status = :status,
                 sale_status = :sale_status,
                 price = :price,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND tenant_id = :tenant_id"
        );

        $stmt->execute([
            'title' => $title,
            'year_created' => trim((string) ($_POST['year_created'] ?? '')) ?: null,
            'medium' => trim((string) ($_POST['medium'] ?? '')) ?: null,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            'status' => $status,
            'sale_status' => $saleStatus,
            'price' => $price !== '' ? $price : null,
            'id' => $id,
            'tenant_id' => $tenant->tenantId,
        ]);

        return Response::html('<h1>Artwork saved</h1><p><a href="/admin/artworks">Back to artworks</a></p>');
    }


    public function updateStatus(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $id = (int) ($_POST['id'] ?? 0);
        $status = (string) ($_POST['status'] ?? 'draft');

        if (!in_array($status, ['draft', 'published', 'archived'], true)) {
            return Response::html('<h1>Invalid status</h1>', 422);
        }

        $stmt = $this->pdo->prepare(
            "UPDATE artworks
             SET status = :status,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND tenant_id = :tenant_id"
        );

        $stmt->execute([
            'status' => $status,
            'id' => $id,
            'tenant_id' => $tenant->tenantId,
        ]);

        return Response::redirect('/admin/artworks?notice=status-updated#artwork-' . $id);
    }


    public function delete(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            return Response::html('<h1>Invalid artwork</h1>', 422);
        }

        $stmt = $this->pdo->prepare(
            "UPDATE artworks
             SET status = 'archived',
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND tenant_id = :tenant_id"
        );

        $stmt->execute([
            'id' => $id,
            'tenant_id' => $tenant->tenantId,
        ]);

        return Response::redirect('/admin/artworks?notice=artwork-archived');
    }

    private function findArtwork(TenantContext $tenant, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM artworks
             WHERE id = :id
               AND tenant_id = :tenant_id
             LIMIT 1"
        );

        $stmt->execute([
            'id' => $id,
            'tenant_id' => $tenant->tenantId,
        ]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

}

// End of file.
