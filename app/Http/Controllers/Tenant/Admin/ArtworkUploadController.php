<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Tenancy\TenantContext;
use App\Platform\Audit\AuditLogRepository;
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
        private readonly ?AuditLogRepository $auditLog = null,
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
<style>
    .upload-status {
        display: none;
        margin-top: 1rem;
        font-weight: 600;
    }

    .spinner {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        margin-right: .5rem;
        border: 2px solid currentColor;
        border-right-color: transparent;
        border-radius: 999px;
        vertical-align: -0.15em;
        animation: spin .8s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    button[disabled] {
        opacity: .65;
        cursor: wait;
    }
</style>

<form id="artwork-upload-form" method="post" action="/admin/artwork/upload" enctype="multipart/form-data">
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
    <button id="artwork-upload-button" type="submit">Upload artwork</button>
    <p id="artwork-upload-status" class="upload-status" role="status" aria-live="polite">
        <span class="spinner" aria-hidden="true"></span>
        Uploading artwork…
    </p>
</form>

<script>
    const form = document.getElementById('artwork-upload-form');
    const button = document.getElementById('artwork-upload-button');
    const status = document.getElementById('artwork-upload-status');

    form?.addEventListener('submit', () => {
        button.disabled = true;
        button.textContent = 'Uploading…';
        status.style.display = 'block';
    });
</script>
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

        if ($this->auditLog !== null) {
            $this->auditLog->record(
                action: 'tenant.artwork.uploaded',
                tenantId: $tenant->tenantId,
                userId: isset($currentUser['id']) ? (int) $currentUser['id'] : null,
                entityType: 'artwork',
                entityId: (string) ($record['artwork_id'] ?? ''),
                details: $record,
                ipAddress: $_SERVER['REMOTE_ADDR'] ?? null,
            );
        }

        $title = htmlspecialchars((string) $record['title'], ENT_QUOTES, 'UTF-8');

        return Response::html("<h1>Artwork uploaded</h1><p>{$title} has been saved as a draft.</p><p><a href=\"/admin/artworks\">Review artworks</a></p>");
    }
}

// End of file.
