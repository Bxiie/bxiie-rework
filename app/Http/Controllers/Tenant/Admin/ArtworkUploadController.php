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
        if (!$this->roles->allows($tenant, $currentUser, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
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
    <p><label>Image<br><input type="file" name="artwork" accept="image/jpeg,image/png,image/webp,image/gif" required></label></p>
    <button type="submit">Upload artwork</button>
</form>
</body>
</html>
HTML);
    }

    public function submit(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($tenant, $currentUser, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }

        try {
            $record = $this->uploads->store($tenant, $_FILES['artwork'] ?? [], (string) ($_POST['title'] ?? ''));
        } catch (\Throwable $e) {
            return Response::html('<h1>Upload failed</h1><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>', 422);
        }

        $title = htmlspecialchars((string) $record['title'], ENT_QUOTES, 'UTF-8');

        return Response::html("<h1>Artwork uploaded</h1><p>{$title} has been staged.</p>");
    }
}

// End of file.
