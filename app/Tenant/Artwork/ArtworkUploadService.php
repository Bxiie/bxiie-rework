<?php

declare(strict_types=1);

namespace App\Tenant\Artwork;

use App\Platform\Tenancy\TenantContext;
use RuntimeException;

/**
 * Stages tenant artwork uploads until the final artwork/media schema is locked.
 */
final class ArtworkUploadService
{
    public function store(TenantContext $tenant, array $file, array $metadata): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Artwork upload failed.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Invalid uploaded file.');
        }

        $mime = mime_content_type($tmp) ?: 'application/octet-stream';
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

        if (!isset($extensions[$mime])) {
            throw new RuntimeException('Artwork must be JPEG, PNG, WebP, or GIF.');
        }

        $root = dirname(__DIR__, 3);
        $dir = "{$root}/storage/uploads/artwork/{$tenant->slug}";
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $sha256 = hash_file('sha256', $tmp);
        $target = "{$dir}/{$sha256}.{$extensions[$mime]}";

        if (!is_file($target) && !move_uploaded_file($tmp, $target)) {
            throw new RuntimeException('Could not store artwork upload.');
        }

        $record = [
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
            'title' => trim((string) ($metadata['title'] ?? '')) !== '' ? trim((string) $metadata['title']) : 'Untitled artwork',
            'artwork_date' => trim((string) ($metadata['artwork_date'] ?? '')),
            'medium' => trim((string) ($metadata['medium'] ?? '')),
            'notes' => trim((string) ($metadata['notes'] ?? '')),
            'sale_status' => in_array(($metadata['sale_status'] ?? 'nfs'), ['nfs', 'for_sale', 'sold'], true) ? $metadata['sale_status'] : 'nfs',
            'price' => trim((string) ($metadata['price'] ?? '')),
            'stored_path' => $target,
            'sha256' => $sha256,
            'mime_type' => $mime,
            'uploaded_at' => date('c'),
        ];

        file_put_contents("{$dir}/manifest.jsonl", json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);

        return $record;
    }
}

// End of file.
