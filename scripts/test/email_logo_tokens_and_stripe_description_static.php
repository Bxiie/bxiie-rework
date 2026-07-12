<?php

declare(strict_types=1);

/** Regression checks for email logo tokens, Stripe descriptions, and removed operations copy. */

$root = dirname(__DIR__, 2);
$failures = [];
$branded = (string) file_get_contents($root . '/app/Platform/Email/BrandedEmail.php');
$stripe = (string) file_get_contents($root . '/app/Tenant/Sales/StripeCheckoutService.php');
$controller = (string) file_get_contents($root . '/app/Http/Controllers/Platform/Admin/EmailTemplatesController.php');
$operations = (string) file_get_contents($root . '/app/Http/Controllers/Platform/Admin/OperationsController.php');

foreach (["self::logoHtml('small')", "self::logoHtml('medium')", "self::logoHtml('large')", "'small' => 120", "'large' => 280", 'stripLogoTokens', 'containsLogoToken'] as $marker) {
    if (!str_contains($branded, $marker)) $failures[] = "BrandedEmail missing marker: {$marker}";
}
foreach (['logo', 'logo-large', 'logo-small'] as $token) {
    if (!str_contains($controller, "'{$token}' => ['All email templates'")) $failures[] = "Placeholder reference missing: {$token}";
}
if (!str_contains($stripe, "'ArtsFolio: ' . \$descriptionDetail")) $failures[] = 'Stripe description prefix is missing.';
foreach (['Backup protection:', 'Share this page URL with another platform administrator.'] as $removed) {
    if (str_contains($operations, $removed)) $failures[] = "Removed operations copy remains: {$removed}";
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/template/email', FilesystemIterator::SKIP_DOTS));
$count = 0;
foreach ($iterator as $file) {
    if (!$file->isFile() || str_starts_with($file->getFilename(), '._') || !in_array(strtolower($file->getExtension()), ['md', 'txt', 'html'], true)) continue;
    $count++;
    $contents = (string) file_get_contents($file->getPathname());
    if (!preg_match('/\{\{\s*logo(?:-(?:small|large))?\s*\}\}/i', $contents)) $failures[] = 'Template missing logo token: ' . $file->getPathname();
}
if ($count < 1) $failures[] = 'No email templates were checked.';

require_once $root . '/app/Platform/Email/BrandedEmail.php';
$html = \App\Platform\Email\BrandedEmail::htmlFromText('Logo token test', "{{logo-small}}\n\nSmall\n\n{{logo}}\n\nMedium\n\n{{logo-large}}\n\nLarge");
$textBody = \App\Platform\Email\BrandedEmail::text("{{logo}}\n\nHello from ArtsFolio");
foreach (['width="120"', 'width="180"', 'width="280"'] as $width) {
    if (!str_contains($html, $width)) $failures[] = "Rendered HTML missing {$width}.";
}
if (str_contains($textBody, '{{logo')) $failures[] = 'Plain-text email leaked a logo token.';

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Email-logo/Stripe/operations regression failed:\n");
    foreach ($failures as $failure) fwrite(STDERR, "[FAIL]  - {$failure}\n");
    exit(1);
}

echo "[PASS] Email-logo tokens, Stripe descriptions, and operations copy passed.\n";

// End of file.
