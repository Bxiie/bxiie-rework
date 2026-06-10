<?php

declare(strict_types=1);

/**
 * Static/runtime regression checks for custom SMTP headers.
 *
 * Postmark message stream routing depends on headers such as:
 *   X-PM-Message-Stream: broadcasts
 */

$root = dirname(__DIR__, 2);
$smtpFile = $root . '/app/Platform/Email/SmtpEmailSender.php';

if (!is_file($smtpFile)) {
    fwrite(STDERR, "Missing SmtpEmailSender.php\n");
    exit(1);
}

$source = file_get_contents($smtpFile);

if ($source === false) {
    fwrite(STDERR, "Unable to read SmtpEmailSender.php\n");
    exit(1);
}

$markers = [
    'custom header loop' => '$this->headers as $name => $value',
    'safe header validation' => 'assertSafeHeader',
    'header rendering loop' => 'foreach ($headers as $name => $value)',
    'multipart support preserved' => 'multipart/alternative',
];

foreach ($markers as $label => $needle) {
    if (strpos($source, $needle) === false) {
        fwrite(STDERR, "Missing SMTP custom header marker: {$label}\n");
        exit(1);
    }
}

require $root . '/bootstrap/app.php';

use App\Platform\Email\SmtpEmailSender;

$sender = new SmtpEmailSender(
    'localhost',
    25,
    'info@artsfol.io',
    'ArtsFolio',
    30,
    null,
    [
        'X-PM-Message-Stream' => 'broadcasts',
    ]
);

if (!method_exists($sender, 'buildMessageForTest')) {
    fwrite(STDERR, "SmtpEmailSender must expose buildMessageForTest() for deterministic preflight.\n");
    exit(1);
}

$message = $sender->buildMessageForTest([
    'recipient_email' => 'test@example.test',
    'subject' => 'SMTP header test',
    'body_text' => 'Plain text body.',
    'body_html' => '<p>HTML body.</p>',
]);

$requiredFragments = [
    'X-PM-Message-Stream: broadcasts',
    'Content-Type: multipart/alternative',
    'Content-Type: text/html; charset=UTF-8',
];

foreach ($requiredFragments as $fragment) {
    if (strpos($message, $fragment) === false) {
        fwrite(STDERR, "Missing expected SMTP message fragment: {$fragment}\n");
        exit(1);
    }
}

echo "SMTP custom headers are rendered safely.\n";

// End of file.
