<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Tenancy\TenantContext;
use PDO;

final class ArtworksController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly PDO $pdo,
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
                m.storage_path,
                m.mime_type,
                m.width,
                m.height
             FROM artworks a
             LEFT JOIN media_assets m ON m.id = a.primary_media_id
             WHERE a.tenant_id = :tenant_id
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
                $src = '/media/' . ltrim((string) $row['storage_path'], '/');
                $src = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
                $image = "<img src=\"{$src}\" alt=\"{$title}\" style=\"max-width:180px;max-height:140px;object-fit:contain;border:1px solid #ddd;background:#fff;\">";
            }

            $items .= <<<HTML
<tr>
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
</tr>
HTML;
        }

        if ($items === '') {
            $items = '<tr><td colspan="8">No artwork uploaded yet.</td></tr>';
        }

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
}

// End of file.
