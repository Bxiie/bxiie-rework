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
use App\Tenant\Artwork\ArtworkUploadService;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class ArtworkBulkUploadController
{
    public function __construct(private readonly RequireTenantRoleBrowser $roles, private readonly PDO $pdo, private readonly CsrfTokenService $csrf, private readonly ArtworkUploadService $uploads)
    {
    }

    public function form(Request $request, TenantContext $tenant, ?array $user): Response
    {
        if (!$this->allowed($user, $tenant)) return Response::error(403, 'Tenant admin access required.');
        $token = AdminLayout::escape($this->csrf->getOrCreate());
        $body = '<p><a class="admin-button" href="/admin/artwork/bulk/sample.csv">Download sample spreadsheet</a></p><p>Select one directory containing <code>artworks.csv</code> and every image named in its <code>filename</code> column. CSV is used so the spreadsheet can be edited in Excel, Numbers, Google Sheets, or LibreOffice.</p><form method="post" action="/admin/artwork/bulk" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="' . $token . '"><label>Import directory<br><input type="file" name="directory[]" webkitdirectory directory multiple required></label><button type="submit">Validate and import artworks</button></form>';
        return Response::html(AdminLayout::render('Bulk Artwork Upload', $body, 'artworks'));
    }

    public function sample(Request $request, TenantContext $tenant, ?array $user): Response
    {
        if (!$this->allowed($user, $tenant)) return Response::error(403, 'Tenant admin access required.');
        $csv = "filename,title,artwork_date,medium,notes,sale_status,price,status,publish_at,release_group,sections,artwork_types\nscheduled-example.jpg,Scheduled artwork,2026,Oil on canvas,Public description,nfs,,draft,2026-10-01 09:00,,Paintings|New Work,portfolio_images\nrelease-example.jpg,Release artwork,2026,Mixed media,Public description,for_sale,1200,draft,,Autumn Release,New Work,portfolio_images\n";
        return new Response($csv, 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="artsfolio-bulk-artwork-sample.csv"']);
    }

    public function submit(Request $request, TenantContext $tenant, ?array $user): Response
    {
        if (!$this->allowed($user, $tenant)) return Response::error(403, 'Tenant admin access required.');
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) return Response::invalidCsrf();
        $files = $this->files($_FILES['directory'] ?? []);
        $sheet = null;
        $byName = [];
        foreach ($files as $file) {
            $name = strtolower(basename((string) ($file['full_path'] ?: $file['name'])));
            if ($name === 'artworks.csv') $sheet = $file;
            if (isset($byName[$name])) return Response::error(422, 'Duplicate filename in selected directory: ' . $name);
            $byName[$name] = $file;
        }
        if (!$sheet) return Response::error(422, 'The selected directory must contain artworks.csv.');
        [$headers, $rows] = $this->csv((string) $sheet['tmp_name']);
        if (!in_array('filename', $headers, true) || !in_array('title', $headers, true)) return Response::error(422, 'artworks.csv requires filename and title columns.');
        $imported = 0;
        $errors = [];
        $usedImages = [];
        foreach ($rows as $index => $row) {
            $filename = strtolower(basename(trim((string) ($row['filename'] ?? ''))));
            if ($filename === '' || !isset($byName[$filename])) {
                $errors[] = 'Row ' . ($index + 2) . ': image file not found for ' . ($row['filename'] ?? '(blank)');
                continue;
            }
            if (isset($usedImages[$filename])) {
                $errors[] = 'Row ' . ($index + 2) . ': image filename is already used by another row: ' . $filename;
                continue;
            }
            $usedImages[$filename] = true;
            try {
                $publishAt = $this->scheduledUtc((string) ($row['publish_at'] ?? ''));
                $releaseGroup = trim((string) ($row['release_group'] ?? ''));
                if ($publishAt !== null && $releaseGroup !== '') {
                    throw new \InvalidArgumentException('use either publish_at or release_group, not both.');
                }
                $record = $this->uploads->store($tenant, $byName[$filename], [
                    'title' => (string) ($row['title'] ?? ''), 'artwork_date' => (string) ($row['artwork_date'] ?? ''),
                    'medium' => (string) ($row['medium'] ?? ''), 'notes' => (string) ($row['notes'] ?? ''),
                    'sale_status' => (string) ($row['sale_status'] ?? 'nfs'), 'price' => (string) ($row['price'] ?? ''),
                    'status' => strtolower((string) ($row['status'] ?? 'draft')) === 'published' ? 'published' : 'draft',
                ]);
                $artworkId = (int) $record['artwork_id'];
                $this->schedule($tenant->tenantId, $artworkId, $publishAt);
                $this->assignGroup($tenant->tenantId, $artworkId, $releaseGroup, (int) ($user['user_id'] ?? 0));
                $this->assignSections($tenant->tenantId, $artworkId, (string) ($row['sections'] ?? ''));
                $this->assignTypes($artworkId, (string) ($row['artwork_types'] ?? 'portfolio_images'));
                ++$imported;
            } catch (\Throwable $e) {
                $errors[] = 'Row ' . ($index + 2) . ': ' . $e->getMessage();
            }
        }
        (new BackgroundJobRepository($this->pdo))->enqueueSingleton('artwork.publish_due', ['interval_seconds' => 60], null, 0);
        $items = implode('', array_map(static fn (string $error): string => '<li>' . AdminLayout::escape($error) . '</li>', $errors));
        $body = '<p class="admin-notice"><strong>' . $imported . ' artwork(s) imported.</strong></p>' . ($items !== '' ? '<h2>Rows not imported</h2><ul>' . $items . '</ul>' : '') . '<p><a class="admin-button" href="/admin/artworks">View artworks</a></p>';
        return Response::html(AdminLayout::render('Bulk Upload Results', $body, 'artworks'), $errors === [] ? 200 : 422);
    }

    private function files(array $input): array
    {
        $files = [];
        foreach ((array) ($input['name'] ?? []) as $i => $name) {
            $files[] = ['name' => $name, 'full_path' => (string) (($input['full_path'][$i] ?? $name)), 'type' => $input['type'][$i] ?? '', 'tmp_name' => $input['tmp_name'][$i] ?? '', 'error' => $input['error'][$i] ?? UPLOAD_ERR_NO_FILE, 'size' => $input['size'][$i] ?? 0];
        }
        return $files;
    }

    private function csv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (!$handle) throw new \RuntimeException('Unable to read artworks.csv.');
        $headers = array_map(static fn ($v) => strtolower(trim((string) $v)), fgetcsv($handle) ?: []);
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if (count(array_filter($values, static fn ($v) => trim((string) $v) !== '')) === 0) continue;
            $values = array_pad($values, count($headers), '');
            $rows[] = array_combine($headers, array_slice($values, 0, count($headers))) ?: [];
        }
        fclose($handle);
        return [$headers, $rows];
    }

    private function scheduledUtc(string $value): ?string
    {
        if (trim($value) === '') return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i', trim($value), new DateTimeZone((string) ($GLOBALS['artsfolio_user_timezone'] ?? 'UTC')));
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new \InvalidArgumentException('publish_at must use YYYY-MM-DD HH:MM.');
        }
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function schedule(int $tenantId, int $artworkId, ?string $publishAt): void
    {
        if ($publishAt === null) return;
        $stmt = $this->pdo->prepare("UPDATE artworks SET status = 'draft', scheduled_publish_at = :publish_at WHERE id = :id AND tenant_id = :tenant_id");
        $stmt->execute(['publish_at' => $publishAt, 'id' => $artworkId, 'tenant_id' => $tenantId]);
    }

    private function assignGroup(int $tenantId, int $artworkId, string $name, int $userId): void
    {
        if ($name === '') return;
        $stmt = $this->pdo->prepare("INSERT INTO artwork_release_groups (tenant_id, name, created_by_user_id, created_at, updated_at) VALUES (:tenant_id, :name, :user_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)");
        $stmt->execute(['tenant_id' => $tenantId, 'name' => $name, 'user_id' => $userId ?: null]);
        $groupId = (int) $this->pdo->lastInsertId();
        $item = $this->pdo->prepare('INSERT INTO artwork_release_group_items (release_group_id, tenant_id, artwork_id) VALUES (:group_id, :tenant_id, :artwork_id) ON DUPLICATE KEY UPDATE release_group_id = VALUES(release_group_id)');
        $item->execute(['group_id' => $groupId, 'tenant_id' => $tenantId, 'artwork_id' => $artworkId]);
    }

    private function assignSections(int $tenantId, int $artworkId, string $names): void
    {
        foreach (array_filter(array_map('trim', explode('|', $names))) as $name) {
            $section = $this->pdo->prepare('SELECT id FROM portfolio_sections WHERE tenant_id = :tenant_id AND LOWER(name) = LOWER(:name) LIMIT 1');
            $section->execute(['tenant_id' => $tenantId, 'name' => $name]);
            $id = (int) $section->fetchColumn();
            if ($id > 0) $this->pdo->prepare('INSERT IGNORE INTO artwork_section_assignments (artwork_id, section_id, created_at) VALUES (:artwork_id, :section_id, CURRENT_TIMESTAMP)')->execute(['artwork_id' => $artworkId, 'section_id' => $id]);
        }
    }

    private function assignTypes(int $artworkId, string $codes): void
    {
        foreach (array_filter(array_map('trim', explode('|', $codes))) as $code) {
            $stmt = $this->pdo->prepare('INSERT IGNORE INTO artwork_type_assignments (artwork_id, type_id, created_at) SELECT :artwork_id, id, CURRENT_TIMESTAMP FROM artwork_types WHERE code = :code');
            $stmt->execute(['artwork_id' => $artworkId, 'code' => $code]);
        }
    }

    private function allowed(?array $user, TenantContext $tenant): bool
    {
        return $this->roles->allows($user, $tenant, ['tenant_owner','tenant_admin','owner','admin']);
    }
}
