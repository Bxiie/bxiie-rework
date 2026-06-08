<?php

declare(strict_types=1);

/**
 * Static checks for the ArtsFolio email HTML/logo pipeline.
 *
 * This catches the failure mode where templates exist but the outbound email is
 * still plain text because body_html was not produced.
 */

$root = dirname(__DIR__, 2);

$files = [
    'BrandedEmail' => $root . '/app/Platform/Email/BrandedEmail.php',
    'EmailOutboxRepository' => $root . '/app/Platform/Email/EmailOutboxRepository.php',
];

foreach ($files as $label => $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Missing {$label}: {$file}\n");
        exit(1);
    }
}

$branded = file_get_contents($files['BrandedEmail']);
$outbox = file_get_contents($files['EmailOutboxRepository']);

if ($branded === false || $outbox === false) {
    fwrite(STDERR, "Unable to read email pipeline source files\n");
    exit(1);
}

$brandedNeedles = [
    'text compatibility method' => 'public static function text',
    'html compatibility method' => 'public static function html',
    'render method' => 'public static function render',
    'body_html payload' => "'body_html'",
    'logo helper' => 'logoHtml',
    'logo asset' => 'logo_2.png',
    'logo injection' => 'self::logoHtml()',
];

foreach ($brandedNeedles as $label => $needle) {
    if (strpos($branded, $needle) === false) {
        fwrite(STDERR, "Missing branded email marker: {$label}\n");
        exit(1);
    }
}

$outboxNeedles = [
    'outbox body_html generation' => 'BrandedEmail::render',
    'outbox body_text storage' => 'body_text',
    'outbox body_html storage' => 'body_html',
];

foreach ($outboxNeedles as $label => $needle) {
    if (strpos($outbox, $needle) === false) {
        fwrite(STDERR, "Missing outbox email marker: {$label}\n");
        exit(1);
    }
}

echo "Email HTML/logo pipeline static checks passed.\n";

// End of file.
