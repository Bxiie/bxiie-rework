<?php
declare(strict_types=1);

/**
 * Regression checks for SMTP suppression of reserved test recipients.
 */

$projectRoot = dirname(__DIR__, 2);
$senderPath = $projectRoot . '/app/Platform/Email/SmtpEmailSender.php';

$senderContents = file_get_contents($senderPath);

if ($senderContents === false) {
    fwrite(STDERR, "SMTP reserved recipient guard static check failed: unable to read SmtpEmailSender.php.\n");
    exit(1);
}

$failures = [];

foreach ([
    'reserved_test_recipient',
    'isReservedTestRecipient',
    "['.test', '.invalid', '.example', '.localhost']",
    'suppressed',
] as $requiredText) {
    if (!str_contains($senderContents, $requiredText)) {
        $failures[] = "SmtpEmailSender.php missing: {$requiredText}";
    }
}

$guardPosition = strpos($senderContents, 'isReservedTestRecipient($recipientEmail)');
$connectPosition = strpos($senderContents, '$socket = $this->connect();');

if ($guardPosition === false || $connectPosition === false || $guardPosition > $connectPosition) {
    $failures[] = 'Reserved-recipient suppression must occur before the SMTP connection.';
}

if ($failures !== []) {
    fwrite(
        STDERR,
        "SMTP reserved recipient guard static check failed:\n - "
        . implode("\n - ", $failures)
        . "\n"
    );
    exit(1);
}

fwrite(STDOUT, "SMTP reserved recipient guard static checks passed.\n");

/* End of file. */