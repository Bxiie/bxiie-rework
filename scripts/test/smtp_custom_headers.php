<?php

declare(strict_types=1);

require __DIR__ . '/../../bootstrap/app.php';

use App\Platform\Email\SmtpEmailSender;

$sender = new SmtpEmailSender(
    host: '127.0.0.1',
    port: 1025,
    fromEmail: 'no-reply@artsfol.io',
    fromName: 'ArtsFolio',
    headers: [
        'X-PM-Message-Stream' => 'broadcasts',
        'X-PM-Tag' => 'welcome-email',
    ],
);

$message = $sender->buildMessageForTest([
    'id' => 1,
    'recipient_email' => 'admin@example.test',
    'subject' => 'Header test',
    'body_text' => "Hello\nWorld",
]);

$required = [
    "X-PM-Message-Stream: broadcasts\r\n",
    "X-PM-Tag: welcome-email\r\n",
    "\r\n\r\nHello\r\nWorld",
];

foreach ($required as $needle) {
    if (!str_contains($message, $needle)) {
        fwrite(STDERR, "Missing expected SMTP message fragment: {$needle}\n");
        exit(1);
    }
}

try {
    new SmtpEmailSender(
        host: '127.0.0.1',
        port: 1025,
        fromEmail: 'no-reply@artsfol.io',
        headers: ['X-Bad' => "one\r\nInjected: two"],
    );
    fwrite(STDERR, "Unsafe SMTP header value was accepted.\n");
    exit(1);
} catch (InvalidArgumentException) {
    // Expected. Header values must not allow CRLF injection.
}

putenv('EMAIL_DRIVER=smtp');
putenv('SMTP_X_PM_MESSAGE_STREAM=outbound');
putenv('SMTP_EXTRA_HEADERS=X-PM-Tag: lifecycle; X-PM-Metadata-tenant: bxiie');

$factory = App\Platform\Email\EmailSenderFactory::fromEnvironment();
$factoryMessage = $factory->buildMessageForTest([
    'id' => 2,
    'recipient_email' => 'admin@example.test',
    'subject' => 'Factory header test',
    'body_text' => 'Hello',
]);

foreach ([
    "X-PM-Message-Stream: outbound\r\n",
    "X-PM-Tag: lifecycle\r\n",
    "X-PM-Metadata-tenant: bxiie\r\n",
] as $needle) {
    if (!str_contains($factoryMessage, $needle)) {
        fwrite(STDERR, "Factory did not add expected SMTP header: {$needle}\n");
        exit(1);
    }
}

echo "SMTP custom headers are rendered safely.\n";

// End of file.
