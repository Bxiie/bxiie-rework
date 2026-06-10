<?php

declare(strict_types=1);

/**
 * Manual verification script for email sender factory selection.
 */

use App\Platform\Email\DryRunEmailSender;
use App\Platform\Email\EmailSenderFactory;
use App\Platform\Email\SmtpEmailSender;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$sender = EmailSenderFactory::fromEnvironment();

echo json_encode([
    'sender_class' => get_class($sender),
    'is_dry_run' => $sender instanceof DryRunEmailSender,
    'is_smtp' => $sender instanceof SmtpEmailSender,
    'email_driver' => getenv('EMAIL_DRIVER') ?: 'dry_run',
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
