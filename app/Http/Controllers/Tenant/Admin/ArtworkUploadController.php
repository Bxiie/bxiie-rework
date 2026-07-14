<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;


use App\Http\View\ErrorPage;
use App\Http\View\AdminLayout;
use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Tenancy\TenantContext;
use App\Platform\Audit\AuditLogRepository;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Artwork\ArtworkUploadService;
use App\Tenant\Sales\ArtworkSaleAdminForm;
use PDO;

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
        private readonly ?PDO $pdo = null,
    ) {
    }

    public function form(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin', 'editor', 'user'])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        $csrf = htmlspecialchars($this->csrf->getOrCreate(), ENT_QUOTES, 'UTF-8');
        $uploadNotice = $this->uploadNoticeHtml();
        $contributorDraftNotice = $this->isTenantAdmin($currentUser, $tenant)
            ? ''
            : '<p class="admin-notice">Contributor uploads are saved as drafts and require administrator review before publication.</p>';
        $saleFieldset = $this->pdo !== null ? (new ArtworkSaleAdminForm($this->pdo))->render($tenant->tenantId) : '';

        $body = <<<HTML
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

{$uploadNotice}
{$contributorDraftNotice}
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
    <fieldset>
        <legend>Artwork types</legend>
        <p>Images can be public portfolio work, site-only material for about/contact/background pickers, or both.</p>
        <label><input type="checkbox" name="artwork_types[]" value="portfolio_images" checked> Portfolio Images</label>
        <label><input type="checkbox" name="artwork_types[]" value="site_images"> Site Images</label>
    </fieldset>
    {$saleFieldset}
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
HTML;

        return Response::html(AdminLayout::render('Upload artwork', $body, 'artworks'));
    }

    public function submit(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin', 'editor', 'user'])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::invalidCsrf();
        }

        try {
            $record = $this->uploads->store($tenant, $_FILES['artwork'] ?? [], [
                'title' => (string) ($_POST['title'] ?? ''),
                'artwork_date' => (string) ($_POST['artwork_date'] ?? ''),
                'medium' => (string) ($_POST['medium'] ?? ''),
                'notes' => (string) ($_POST['notes'] ?? ''),
                'sale_status' => (string) ($_POST['sale_status'] ?? 'nfs'),
                'price' => (string) ($_POST['price'] ?? ''),
                'status' => $this->isTenantAdmin($currentUser, $tenant) ? $this->newArtworkDefaultStatus($tenant) : 'draft',
            ]);
        } catch (\Throwable $e) {
            return Response::html('<h1>Upload failed</h1><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>', 422);
        }

        if (!empty($record['artwork_id'])) {
            $artworkId = (int) $record['artwork_id'];
            $this->replaceArtworkTypes($artworkId, $_POST['artwork_types'] ?? ['portfolio_images']);
            if ($this->isTenantAdmin($currentUser, $tenant)) {
                $this->updateSalesInventory($tenant, $artworkId, $_POST);
            }
        }

        if ($this->auditLog !== null) {
            try {
                $this->auditLog->record(
                    action: 'tenant.artwork.uploaded',
                    tenantId: $tenant->tenantId,
                    userId: $this->validAuditUserId($currentUser),
                    entityType: 'artwork',
                    entityId: (string) ($record['artwork_id'] ?? ''),
                    details: $record,
                    ipAddress: $_SERVER['REMOTE_ADDR'] ?? null,
                );
            } catch (\Throwable) {
                // Upload success must not be rolled back by non-critical audit logging.
            }
        }

        $query = [
            'uploaded' => '1',
            'title' => (string) ($record['title'] ?? ''),
            'status' => (string) ($record['status'] ?? 'draft'),
        ];

        return new Response('', 303, ['Location' => '/admin/artwork/upload?' . http_build_query($query)]);
    }

    /**
     * @param mixed $rawTypes
     */

    /**
     * Renders the branded post-upload acknowledgement on the upload form.
     *
     * Upload success redirects back here so the form fields and file input are
     * naturally clear and ready for the next image.
     */
    private function uploadNoticeHtml(): string
    {
        if ((string) ($_GET['uploaded'] ?? '') !== '1') {
            return '';
        }

        $title = trim((string) ($_GET['title'] ?? ''));
        $status = (string) ($_GET['status'] ?? 'draft');
        $titlePrefix = $title !== '' ? '<strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong> ' : '';
        $statusText = $status === 'published'
            ? 'has been uploaded and published.'
            : 'has been uploaded and saved as an unpublished draft.';

        return '<p class="admin-notice admin-notice-success"><strong>Artwork uploaded.</strong> '
            . $titlePrefix
            . htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8')
            . ' The form is ready for the next image.</p>';
    }

    private function replaceArtworkTypes(int $artworkId, mixed $rawTypes): void
    {
        if ($this->pdo === null) {
            return;
        }

        $allowed = ['portfolio_images', 'site_images'];
        $codes = [];
        if (is_array($rawTypes)) {
            foreach ($rawTypes as $code) {
                $code = (string) $code;
                if (in_array($code, $allowed, true)) {
                    $codes[] = $code;
                }
            }
        }
        if (!$codes) {
            $codes = ['portfolio_images'];
        }
        $codes = array_values(array_unique($codes));

        $this->pdo->prepare('DELETE FROM artwork_type_assignments WHERE artwork_id = :artwork_id')->execute(['artwork_id' => $artworkId]);
        $lookup = $this->pdo->prepare('SELECT id FROM artwork_types WHERE code = :code LIMIT 1');
        $insert = $this->pdo->prepare('INSERT IGNORE INTO artwork_type_assignments (artwork_id, type_id, created_at) VALUES (:artwork_id, :type_id, CURRENT_TIMESTAMP)');

        foreach ($codes as $code) {
            $lookup->execute(['code' => $code]);
            $row = $lookup->fetch();
            if (!$row) {
                continue;
            }
            $insert->execute(['artwork_id' => $artworkId, 'type_id' => (int) $row['id']]);
        }
    }


    /**
     * Persists first-pass sales inventory metadata after the artwork row is created.
     *
     * The upload service owns file/media creation. Sales inventory is admin form
     * metadata, so it is patched onto the row here until the broader sales
     * subsystem centralizes catalog writes.
     */
    /**
     * Persist both legacy artwork inventory fields and the phase-one sale catalog.
     *
     * The upload service creates the artwork row first. Once the row exists,
     * this method records the richer checkout configuration used by later cart
     * phases while keeping current public cart behavior compatible.
     *
     * @param array<string,mixed> $post
     */
    private function updateSalesInventory(TenantContext $tenant, int $artworkId, array $post): void
    {
        if ($this->pdo === null) {
            return;
        }

        $salesForm = new ArtworkSaleAdminForm($this->pdo);
        $legacySalesInventory = $salesForm->legacyInventoryFromPost($post);

        $stmt = $this->pdo->prepare(
            'UPDATE artworks SET is_one_off = :is_one_off, inventory_quantity = :inventory_quantity, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'is_one_off' => $legacySalesInventory['is_one_off'],
            'inventory_quantity' => $legacySalesInventory['inventory_quantity'],
            'id' => $artworkId,
            'tenant_id' => $tenant->tenantId,
        ]);

        $salesForm->saveFromPost($tenant->tenantId, $artworkId, $post, (string) ($post['sale_status'] ?? 'nfs'));
    }


    /**
     * Returns the tenant preference used for newly uploaded artwork.
     */
    private function newArtworkDefaultStatus(TenantContext $tenant): string
    {
        if ($this->pdo === null) {
            return 'draft';
        }

        $stmt = $this->pdo->prepare(
            "SELECT setting_value FROM tenant_settings WHERE tenant_id = :tenant_id AND setting_key = 'new_artwork_default_status' LIMIT 1"
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId]);
        $status = (string) ($stmt->fetchColumn() ?: 'draft');

        return in_array($status, ['draft', 'published'], true) ? $status : 'draft';
    }

    private function isTenantAdmin(?array $currentUser, TenantContext $tenant): bool
    {
        return $this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin']);
    }

    private function validAuditUserId(?array $currentUser): ?int
    {
        if (!isset($currentUser['id'])) {
            return null;
        }

        $id = (int) $currentUser['id'];

        return $id > 0 ? $id : null;
    }

}

// End of file.
