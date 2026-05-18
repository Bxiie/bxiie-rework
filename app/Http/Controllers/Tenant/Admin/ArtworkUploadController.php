<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Artwork\ArtworkUploadService;

/**
 * Minimal tenant-admin artwork upload screen.
 */
final class ArtworkUploadController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly CsrfTokenService $csrf,
        private readonly ArtworkUploadService $uploads,
    ) {
    }

    public function form(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $csrf = htmlspecialchars($this->csrf->getOrCreate(), ENT_QUOTES, 'UTF-8');

        return Response::html(<<<HTML
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Upload artwork</title><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body>
<h1>Upload artwork</h1>
<form method="post" action="/admin/artwork/upload" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <p><label>Title<br><input type="text" name="title" required></label></p>
    <p><label>Date / year<br><input type="text" name="artwork_date" placeholder="2026, 2021-2024, or exact date"></label></p>
    <p><label>Medium<br><input type="text" name="medium" placeholder="3D printed plastic, steel, wood, digital media"></label></p>
    <p><label>Notes<br><textarea name="notes" rows="5"></textarea></label></p>
    <p>
        <label>Sale status<br>
            <select name="sale_status">
                <option value="nfs">NFS</option>
                <option value="for_sale">For sale</option>
                <option value="sold">Sold</option>
            </select>
        </label>
    </p>
    <p><label>Price<br><input type="text" name="price" placeholder="1200, 1200 USD, contact for price"></label></p>
    <p><label>Image<br><input type="file" name="artwork" accept="image/jpeg,image/png,image/webp,image/gif" required></label></p>
    <button type="submit">Upload artwork</button>
</form>
</body>
</html>
HTML);
    }

    public function submit(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }

        try {
            $record = $this->uploads->store($tenant, $_FILES['artwork'] ?? [], [
                'title' => (string) ($_POST['title'] ?? ''),
                'artwork_date' => (string) ($_POST['artwork_date'] ?? ''),
                'medium' => (string) ($_POST['medium'] ?? ''),
                'notes' => (string) ($_POST['notes'] ?? ''),
                'sale_status' => (string) ($_POST['sale_status'] ?? 'nfs'),
                'price' => (string) ($_POST['price'] ?? ''),
            ]);
        } catch (\Throwable $e) {
            return Response::html('<h1>Upload failed</h1><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>', 422);
        }

        $title = htmlspecialchars((string) $record['title'], ENT_QUOTES, 'UTF-8');

        return Response::html("<h1>Artwork uploaded</h1><p>{$title} has been staged.</p>");
    }
}

// End of file.
