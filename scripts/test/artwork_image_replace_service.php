<?php

declare(strict_types=1);

use App\Platform\Tenancy\TenantContext;
use App\Tenant\Media\ArtworkImageReplaceService;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function check(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

$projectRoot = sys_get_temp_dir() . '/artsfolio_replace_test_' . getmypid();
if (!is_dir($projectRoot) && !mkdir($projectRoot, 0775, true)) {
    throw new RuntimeException('Could not create replace-image test fixture directory.');
}

$newImagePath = $projectRoot . '/upload-source.png';
$newImage = imagecreatetruecolor(60, 40);
imagefill($newImage, 0, 0, imagecolorallocate($newImage, 200, 20, 20));
imagepng($newImage, $newImagePath);
// PHP 8.5 releases GDImage memory automatically when references leave scope.
imagedestroy($newImage);

$textFilePath = $projectRoot . '/not-an-image.txt';
file_put_contents($textFilePath, 'not an image');

// Exercise real repository/variant-service persistence in an isolated
// database. Translate only the MariaDB upsert syntax; queries, parameters
// and policy run unchanged.
final class ReplaceTestPdo extends Pdo\Sqlite
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'ON DUPLICATE KEY UPDATE')) {
            $query = preg_replace('/ON DUPLICATE KEY UPDATE/', 'ON CONFLICT(media_asset_id, variant_key) DO UPDATE SET', $query);
            $query = preg_replace('/(\w+)\s*=\s*VALUES\((\w+)\)/', '$1 = excluded.$2', (string) $query);
        }

        return parent::prepare($query, $options);
    }
}

$pdo = new ReplaceTestPdo('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->createFunction('UUID', static fn (): string => bin2hex(random_bytes(16)));
$pdo->exec(
    "CREATE TABLE artworks (id INTEGER, tenant_id INTEGER, title TEXT, primary_media_id INTEGER, updated_at TEXT);
     CREATE TABLE media_assets (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT, tenant_id INTEGER, original_filename TEXT, storage_path TEXT, mime_type TEXT, file_size_bytes INTEGER, width INTEGER, height INTEGER, alt_text TEXT, title TEXT, caption TEXT, credit TEXT, is_private INTEGER, created_at TEXT, updated_at TEXT);
     CREATE TABLE media_asset_variants (media_asset_id INTEGER, variant_key TEXT, storage_path TEXT, mime_type TEXT, width INTEGER, height INTEGER, file_size_bytes INTEGER, created_at TEXT, updated_at TEXT, UNIQUE(media_asset_id, variant_key));
     INSERT INTO media_assets (id, uuid, tenant_id, original_filename, storage_path, mime_type, alt_text, title, caption, credit, is_private)
         VALUES (1, 'old-uuid', 1, 'old.png', 'storage/uploads/artwork/test/old.png', 'image/png', 'Old alt text', 'Old title', 'Old caption', 'Old credit', 1);
     INSERT INTO artworks (id, tenant_id, title, primary_media_id) VALUES (1, 1, 'With Image', 1);
     INSERT INTO artworks (id, tenant_id, title, primary_media_id) VALUES (2, 1, 'Without Image', NULL);"
);

$tenant = new TenantContext(1, 'uuid', 'test', 'Test', 'test.local', 'custom', true);
$service = new ArtworkImageReplaceService($pdo, $projectRoot);
$replaceFromFile = new ReflectionMethod($service, 'replaceFromTemporaryFile');

// 1. Replacing an artwork that already has an image: a NEW media_assets row
//    is created (old row untouched, not deleted), artworks.primary_media_id
//    is repointed at it, and its alt_text/title/caption/credit/is_private are
//    carried over from the old media row rather than reset to blank.
$newMediaId = $replaceFromFile->invoke($service, $tenant, 1, $newImagePath, 'My Upload.png');
check($newMediaId !== 1, 'Replacing an image must create a new media_assets row, not reuse the old one');

$media = $pdo->query("SELECT * FROM media_assets WHERE id = {$newMediaId}")->fetch();
check($media !== false, 'The new media_assets row was not created');
check($media['alt_text'] === 'Old alt text', 'alt_text was not carried over from the replaced image');
check($media['title'] === 'Old title', 'title was not carried over from the replaced image');
check($media['caption'] === 'Old caption', 'caption was not carried over from the replaced image');
check($media['credit'] === 'Old credit', 'credit was not carried over from the replaced image');
check((int) $media['is_private'] === 1, 'is_private was not carried over from the replaced image');
check($media['mime_type'] === 'image/png', 'New media row has the wrong mime type');
check((int) $media['width'] === 60 && (int) $media['height'] === 40, 'New media row has the wrong dimensions');

$artwork = $pdo->query('SELECT primary_media_id FROM artworks WHERE id = 1')->fetch();
check((int) $artwork['primary_media_id'] === $newMediaId, 'artworks.primary_media_id was not repointed at the new image');

$oldMediaStillPresent = $pdo->query('SELECT 1 FROM media_assets WHERE id = 1')->fetchColumn();
check($oldMediaStillPresent !== false, 'The old media_assets row must not be deleted (it may still be referenced elsewhere/cached)');

$variantCount = (int) $pdo->query("SELECT COUNT(*) FROM media_asset_variants WHERE media_asset_id = {$newMediaId}")->fetchColumn();
check($variantCount >= 4, 'Variants (original/thumb/medium/large) were not generated for the replacement image');

// 2. Adding an image to an artwork that has none: no "old media" to carry
//    metadata from, so it falls back to the artwork's own title.
$addedMediaId = $replaceFromFile->invoke($service, $tenant, 2, $newImagePath, 'second-upload.png');
$addedMedia = $pdo->query("SELECT * FROM media_assets WHERE id = {$addedMediaId}")->fetch();
check($addedMedia['title'] === 'Without Image', 'New image on an artwork with no prior image should default title to the artwork title');
check($addedMedia['alt_text'] === 'Without Image', 'New image on an artwork with no prior image should default alt_text to the artwork title');
check($addedMedia['caption'] === null, 'New image on an artwork with no prior image should have no carried-over caption');
$artwork2 = $pdo->query('SELECT primary_media_id FROM artworks WHERE id = 2')->fetch();
check((int) $artwork2['primary_media_id'] === $addedMediaId, 'artworks.primary_media_id was not set for an artwork that previously had no image');

// 3. Rejections: wrong file type, and an artwork that does not belong to
//    this tenant (or does not exist).
try {
    $replaceFromFile->invoke($service, $tenant, 1, $textFilePath, 'not-an-image.txt');
    throw new RuntimeException('Expected a non-image upload to be rejected');
} catch (RuntimeException $exception) {
    check(str_contains($exception->getMessage(), 'JPEG, PNG, WebP, or GIF'), 'Unexpected error message for a non-image upload: ' . $exception->getMessage());
}

try {
    $replaceFromFile->invoke($service, $tenant, 999, $newImagePath, 'upload.png');
    throw new RuntimeException('Expected an unknown artwork id to be rejected');
} catch (RuntimeException $exception) {
    check(str_contains($exception->getMessage(), 'Artwork not found'), 'Unexpected error message for an unknown artwork: ' . $exception->getMessage());
}

// 4. Static wiring checks: controller action, role/CSRF gating, route, and
//    that the edit page offers the control for both the "has image" and
//    "no image yet" cases.
$controllerSource = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Http/Controllers/Tenant/Admin/ArtworksController.php');
foreach ([
    'replaceImage method' => 'public function replaceImage(Request $request, TenantContext $tenant, ?array $currentUser): Response',
    'role check' => "if (!\$this->roles->allows(\$currentUser, \$tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {",
    'CSRF check' => '!$this->csrf->validate((string) ($_POST[\'csrf_token\'] ?? \'\'))',
    'delegates to ArtworkImageReplaceService' => 'ArtworkImageReplaceService($this->pdo, dirname(__DIR__, 5))',
    'replace-image form when an image exists' => 'action="/admin/artworks/replace-image" enctype="multipart/form-data"',
    'upload form offered when no image exists yet' => 'This artwork does not currently have a primary image.',
] as $label => $needle) {
    if (!str_contains($controllerSource, $needle)) {
        throw new RuntimeException('ArtworksController missing ' . $label);
    }
}
// Both the "has image" and "no image" branches must offer the file input,
// not just one of them.
if (substr_count($controllerSource, 'name="artwork_image"') < 2) {
    throw new RuntimeException('Expected an artwork_image file input in both the replace and add-image branches.');
}

$routesSource = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Http/Routes/tenant.php');
if (!str_contains($routesSource, "\$router->post('/admin/artworks/replace-image', fn (Request \$request): Response => \$artworksController->replaceImage(\$request, \$tenant, \$currentUser));")) {
    throw new RuntimeException('tenant.php missing the replace-image route registration.');
}

// Clean up fixture files.
@unlink($newImagePath);
@unlink($textFilePath);
foreach (glob($projectRoot . '/storage/uploads/artwork/test/*') ?: [] as $leftover) {
    @unlink($leftover);
}
foreach (glob($projectRoot . '/storage/uploads/artwork/test/variants/*') ?: [] as $leftover) {
    @unlink($leftover);
}
@rmdir($projectRoot . '/storage/uploads/artwork/test/variants');
@rmdir($projectRoot . '/storage/uploads/artwork/test');
@rmdir($projectRoot . '/storage/uploads/artwork');
@rmdir($projectRoot . '/storage/uploads');
@rmdir($projectRoot . '/storage');
@rmdir($projectRoot);

echo "Artwork image replace service checks passed.\n";

// End of file.
