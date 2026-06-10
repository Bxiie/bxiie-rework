<?php

declare(strict_types=1);

/**
 * Runtime regression check for custom SMTP headers and multipart HTML.
 *
 * SMTP messages are CRLF-normalized. Body assertions normalize CRLF back to LF
 * before checking readable content so the test does not fail on correct SMTP
 * line endings.
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

$sourceMarkers = [
    'custom header loop' => '$this->headers as $name => $value',
    'header render loop' => 'foreach ($headers as $name => $value)',
    'multipart support' => 'multipart/alternative',
    'html part' => 'Content-Type: text/html; charset=UTF-8',
    'test message helper' => 'buildMessageForTest',
];

foreach ($sourceMarkers as $label => $needle) {
    if (strpos($source, $needle) === false) {
        fwrite(STDERR, "Missing SMTP source marker: {$label}\n");
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
    [
        'X-PM-Message-Stream' => 'broadcasts',
    ]
);

$message = $sender->buildMessageForTest([
    'recipient_email' => 'test@example.test',
    'subject' => 'SMTP header test',
    'body_text' => "Hello\nWorld",
    'body_html' => '<p>HTML body with logo_2.png.</p>',
]);

$requiredRawFragments = [
    'X-PM-Message-Stream: broadcasts',
    'Content-Type: multipart/alternative',
    'Content-Type: text/html; charset=UTF-8',
    'logo_2.png',
];

foreach ($requiredRawFragments as $fragment) {
    if (strpos($message, $fragment) === false) {
        fwrite(STDERR, "Missing expected SMTP message fragment: {$fragment}\n");
        fwrite(STDERR, "--- SMTP message preview ---\n");
        fwrite(STDERR, substr($message, 0, 1200) . "\n");
        exit(1);
    }
}

$normalizedMessage = str_replace(["\r\n", "\r"], "\n", $message);
$requiredNormalizedFragments = [
    "Hello\nWorld",
    '<p>HTML body with logo_2.png.</p>',
];

foreach ($requiredNormalizedFragments as $fragment) {
    if (strpos($normalizedMessage, $fragment) === false) {
        fwrite(STDERR, "Missing expected normalized SMTP message fragment: {$fragment}\n");
        fwrite(STDERR, "--- SMTP message preview ---\n");
        fwrite(STDERR, substr($normalizedMessage, 0, 1200) . "\n");
        exit(1);
    }
}

echo "SMTP custom headers are rendered safely.\n";

// End of file.
