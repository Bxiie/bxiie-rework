<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\HomeController;
use App\Platform\Tenancy\TenantContext;
use App\Tenant\Artwork\ArtworkReadRepository;
use App\Tenant\Media\ArtworkExportService;
use App\Tenant\Media\WatermarkService;
use App\Tenant\Settings\TenantSettingsRepository;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$projectRoot = sys_get_temp_dir() . '/artsfolio_export_test_' . getmypid();
if (!is_dir($projectRoot) && !mkdir($projectRoot, 0775, true)) {
    throw new RuntimeException('Could not create export test fixture directory.');
}

$sourceWidth = 120;
$sourceHeight = 80;
$image = imagecreatetruecolor($sourceWidth, $sourceHeight);
imagefill($image, 0, 0, imagecolorallocate($image, 10, 120, 200));
imagepng($image, $projectRoot . '/fixture.png');
imagedestroy($image);

$gifPath = $projectRoot . '/fixture.gif';
$gifImage = imagecreate(10, 10);
imagecolorallocate($gifImage, 0, 0, 0);
imagegif($gifImage, $gifPath);
imagedestroy($gifImage);

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec(
    "CREATE TABLE roles (id INTEGER, slug TEXT, scope TEXT);
     CREATE TABLE role_assignments (role_id INTEGER, user_id INTEGER, tenant_id INTEGER);
     CREATE TABLE tenant_settings (tenant_id INTEGER, setting_key TEXT, setting_value TEXT);
     CREATE TABLE artworks (id INTEGER, tenant_id INTEGER, primary_media_id INTEGER);
     CREATE TABLE media_assets (id INTEGER, tenant_id INTEGER, storage_path TEXT, mime_type TEXT, original_filename TEXT);
     INSERT INTO roles VALUES (1,'tenant_owner','tenant'),(2,'user','tenant');
     INSERT INTO role_assignments VALUES (1,1,1),(2,2,1);
     INSERT INTO media_assets VALUES (1,1,'fixture.png','image/png','fixture.png');
     INSERT INTO media_assets VALUES (2,1,'fixture.gif','image/gif','fixture.gif');
     INSERT INTO artworks VALUES (1,1,1);
     INSERT INTO artworks VALUES (2,1,2);
     INSERT INTO artworks VALUES (3,1,NULL);"
);

$tenant = new TenantContext(1, 'uuid', 'test', 'Test', 'test.local', 'custom', true);
$watermark = new WatermarkService(new TenantSettingsRepository($pdo), $pdo);
$service = new ArtworkExportService($pdo, $watermark, $projectRoot);

// 1. Default export (original format, no resize, no watermark) must return
//    the stored file byte-for-byte, so "export the original image" is literal.
$originalBytes = (string) file_get_contents($projectRoot . '/fixture.png');
$result = $service->export($tenant, 1, 'original', null, false);
if ($result['bytes'] !== $originalBytes) {
    throw new RuntimeException('Unmodified original export did not return byte-identical content.');
}
if ($result['mime'] !== 'image/png' || $result['filename'] !== 'fixture.png') {
    throw new RuntimeException('Unmodified original export had unexpected mime/filename: ' . $result['mime'] . ' / ' . $result['filename']);
}

// 2. Format conversion changes the mime type and produces valid, decodable bytes.
$jpeg = $service->export($tenant, 1, 'jpeg', null, false);
if ($jpeg['mime'] !== 'image/jpeg' || !str_ends_with($jpeg['filename'], '.jpg')) {
    throw new RuntimeException('JPEG conversion did not report image/jpeg output.');
}
$decodedJpeg = @imagecreatefromstring($jpeg['bytes']);
if (!$decodedJpeg) {
    throw new RuntimeException('JPEG conversion output is not decodable as an image.');
}
imagedestroy($decodedJpeg);

// 3. Resize caps the longer side without upscaling smaller requests away.
$resized = $service->export($tenant, 1, 'original', 50, false);
$resizedDimensions = getimagesizefromstring($resized['bytes']);
if (!$resizedDimensions || max($resizedDimensions[0], $resizedDimensions[1]) > 50) {
    throw new RuntimeException('Resized export exceeded the requested max dimension.');
}
$resizedDimensions = (array) $resizedDimensions;
$expectedWidth = (int) round($sourceWidth * (50 / max($sourceWidth, $sourceHeight)));
if ((int) $resizedDimensions[0] !== $expectedWidth) {
    throw new RuntimeException('Resized export width did not match the expected scale.');
}

// 4. Asking for a resize larger than the source must not upscale.
$notUpscaled = $service->export($tenant, 1, 'original', 5000, false);
if ($notUpscaled['bytes'] !== $originalBytes) {
    throw new RuntimeException('Export upscaled the image past its source size.');
}

// 5. Watermark requested, but the tenant has not enabled watermarking: the
//    service must fall back to the plain original rather than error or hang.
$plainExport = $service->export($tenant, 1, 'original', null, true);
if ($plainExport['bytes'] !== $originalBytes) {
    throw new RuntimeException('Watermark request with watermarking disabled did not fall back to the original.');
}

// 6. Watermark requested and enabled: output must differ from the original
//    but stay a valid, same-size, same-format image.
$pdo->exec(
    "INSERT INTO tenant_settings VALUES
     (1,'watermark_enabled','1'),
     (1,'watermark_mode','text'),
     (1,'watermark_format','custom'),
     (1,'watermark_text','TEST MARK'),
     (1,'watermark_position','center'),
     (1,'watermark_opacity','0.6'),
     (1,'watermark_size','3'),
     (1,'watermark_color','white')"
);
// TenantSettingsRepository caches a tenant's snapshot after its first read
// (the disabled-watermark check above already triggered that for $watermark),
// so a fresh repository/service pair is needed to see the rows just inserted.
$serviceWithWatermarkSettings = new ArtworkExportService($pdo, new WatermarkService(new TenantSettingsRepository($pdo), $pdo), $projectRoot);
$watermarkedExport = $serviceWithWatermarkSettings->export($tenant, 1, 'original', null, true);
if ($watermarkedExport['bytes'] === $originalBytes) {
    throw new RuntimeException('Watermarked export was identical to the unwatermarked original.');
}
if (!str_ends_with($watermarkedExport['filename'], '-watermarked.png')) {
    throw new RuntimeException('Watermarked export filename was missing the -watermarked suffix.');
}
$watermarkedDimensions = getimagesizefromstring($watermarkedExport['bytes']);
if (!$watermarkedDimensions || [$watermarkedDimensions[0], $watermarkedDimensions[1]] !== [$sourceWidth, $sourceHeight]) {
    throw new RuntimeException('Watermarked export changed the image dimensions.');
}

// 7. An artwork with no primary image, and a GIF source (outside the
//    supported jpeg/png/webp set), must fail with a clear error rather than
//    a fatal error or silently wrong output.
try {
    $service->export($tenant, 3, 'original', null, false);
    throw new RuntimeException('Expected export of an artwork with no image to fail.');
} catch (RuntimeException $exception) {
    if (!str_contains($exception->getMessage(), 'no exportable image')) {
        throw new RuntimeException('Unexpected error message for missing image: ' . $exception->getMessage());
    }
}
try {
    $service->export($tenant, 2, 'original', null, false);
    throw new RuntimeException('Expected export of a GIF source to fail.');
} catch (RuntimeException $exception) {
    if (!str_contains($exception->getMessage(), 'cannot be exported')) {
        throw new RuntimeException('Unexpected error message for unsupported source type: ' . $exception->getMessage());
    }
}

// 8. Public artwork-page export controls must be gated the same way as the
//    existing Edit artwork link: tenant owner/admin only, not every logged-in user.
foreach ([null, 1, 2] as $userId) {
    $controller = new HomeController(new TenantSettingsRepository($pdo), new ArtworkReadRepository($pdo), $pdo, currentUser: $userId === null ? null : ['user_id' => $userId]);
    $form = (new ReflectionMethod($controller, 'artworkExportForm'))->invoke($controller, $tenant, 1);
    $shouldSeeForm = $userId === 1;
    if ($shouldSeeForm && !str_contains($form, 'action="/artwork/export"')) {
        throw new RuntimeException('Tenant owner did not receive the export form.');
    }
    if (!$shouldSeeForm && $form !== '') {
        throw new RuntimeException('Non-owner user unexpectedly received the export form for user ' . var_export($userId, true));
    }
}

// 9. Static wiring checks: controller methods, CSRF checks, and routes exist.
$homeControllerSource = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Http/Controllers/Tenant/HomeController.php');
foreach ([
    'export form call site' => '$body .= $this->artworkExportForm($tenant, (int) $artwork[\'id\']);',
    'exportArtwork method' => 'public function exportArtwork(Request $request, TenantContext $tenant): Response',
    'exportArtwork CSRF check' => '!$this->csrf || !$this->csrf->validate((string) ($_POST[\'csrf_token\'] ?? \'\'))',
] as $label => $needle) {
    if (!str_contains($homeControllerSource, $needle)) {
        throw new RuntimeException('HomeController missing ' . $label);
    }
}

$artworksControllerSource = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Http/Controllers/Tenant/Admin/ArtworksController.php');
foreach ([
    'export form in edit preview' => 'action="/admin/artworks/export"',
    'exportImage method' => 'public function exportImage(Request $request, TenantContext $tenant, ?array $currentUser): Response',
    'exportImage CSRF check' => '!$this->csrf->validate((string) ($_POST[\'csrf_token\'] ?? \'\'))',
] as $label => $needle) {
    if (!str_contains($artworksControllerSource, $needle)) {
        throw new RuntimeException('ArtworksController missing ' . $label);
    }
}

$routesSource = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Http/Routes/tenant.php');
foreach ([
    "\$router->post('/admin/artworks/export', fn (Request \$request): Response => \$artworksController->exportImage(\$request, \$tenant, \$currentUser));",
    "\$router->post('/artwork/export', fn (Request \$request): Response => \$tenantController->exportArtwork(\$request, \$tenant));",
] as $needle) {
    if (!str_contains($routesSource, $needle)) {
        throw new RuntimeException('tenant.php missing expected export route registration.');
    }
}

// Clean up fixture files.
@unlink($projectRoot . '/fixture.png');
@unlink($gifPath);
@rmdir($projectRoot);

echo "Artwork export service checks passed.\n";

// End of file.
