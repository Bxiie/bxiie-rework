<?php

declare(strict_types=1);

/**
 * Static checks for global email logo branding.
 *
 * All HTML email should be generated through App\Platform\Email\BrandedEmail,
 * which injects the ArtsFolio logo into the HTML wrapper.
 */

$root = dirname(__DIR__, 2);
$branded = $root . '/app/Platform/Email/BrandedEmail.php';

if (!is_file($branded)) {
    fwrite(STDERR, "Missing BrandedEmail.php\n");
    exit(1);
}

$source = file_get_contents($branded);
if ($source === false) {
    fwrite(STDERR, "Unable to read BrandedEmail.php\n");
    exit(1);
}

$required = [
    'html wrapper method' => 'htmlFromText',
    'text compatibility method' => 'public static function text',
    'html compatibility method' => 'public static function html',
    'multipart render method' => 'body_html',
    'logo URL helper' => 'artsfolioLogoUrl',
    'logo HTML helper' => 'logoHtml',
    'absolute fallback URL' => 'https://artsfol.io',
    'logo asset' => 'artsfol-wordmark.png',
    'medium logo injection' => "self::logoHtml('medium')",
];

foreach ($required as $label => $needle) {
    if (strpos($source, $needle) === false) {
        fwrite(STDERR, "Missing email logo branding marker: {$label}\n");
        exit(1);
    }
}

$templateDir = $root . '/template/email';
$templates = glob($templateDir . '/**/*.md') ?: [];
$templates = array_merge($templates, glob($templateDir . '/*.md') ?: []);

if ($templates === []) {
    fwrite(STDERR, "No email templates found under template/email\n");
    exit(1);
}

foreach ($templates as $template) {
    $contents = file_get_contents($template);
    if ($contents === false || trim($contents) === '') {
        fwrite(STDERR, "Empty or unreadable email template: {$template}\n");
        exit(1);
    }
}

echo "Global email logo branding static checks passed.\n";

// End of file.
