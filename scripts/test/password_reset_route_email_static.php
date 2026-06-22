<?php

declare(strict_types=1);

/**
 * Static regression checks for password reset routes and HTML/logo email output.
 */

$root = dirname(__DIR__, 2);
$index = $root . '/app/Http/Routes/platform.php';

$files = [
    'front controller' => $index,
    'AuthPage' => $root . '/app/Http/View/AuthPage.php',
    'SMTP sender' => $root . '/app/Platform/Email/SmtpEmailSender.php',
    'BrandedEmail' => $root . '/app/Platform/Email/BrandedEmail.php',
];

foreach ($files as $label => $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Missing {$label}: {$file}
");
        exit(1);
    }
}

$indexSource = file_get_contents($files['front controller']);
$authPage = file_get_contents($files['AuthPage']);
$smtp = file_get_contents($files['SMTP sender']);
$branded = file_get_contents($files['BrandedEmail']);

$checks = [
    'GET reset route' => [$indexSource, "\$router->get('/password/reset'"],
    'POST reset route' => [$indexSource, "\$router->post('/password/reset'"],
    'reset route consumes token' => [$indexSource, 'resetPassword($token, $password)'],
    'reset form renderer' => [$authPage, 'function resetPassword('],
    'reset token field' => [$authPage, 'name="token"'],
    'SMTP multipart output' => [$smtp, 'multipart/alternative'],
    'SMTP HTML part' => [$smtp, 'Content-Type: text/html; charset=UTF-8'],
    'BrandedEmail logo asset' => [$branded, 'logo_2.png'],
];

foreach ($checks as $label => [$source, $needle]) {
    if ($source === false || strpos($source, $needle) === false) {
        fwrite(STDERR, "Missing password reset/email marker: {$label}
");
        exit(1);
    }
}

require $root . '/bootstrap/app.php';

use App\Platform\Email\BrandedEmail;
use App\Platform\Email\SmtpEmailSender;

$payload = BrandedEmail::render('Reset your ArtsFolio password', "Use this link:

https://artsfol.io/password/reset?token=test");
if (strpos($payload['body_html'], 'logo_2.png') === false) {
    fwrite(STDERR, "Branded reset email HTML is missing logo_2.png
");
    exit(1);
}

$sender = new SmtpEmailSender('localhost', 25, 'info@artsfol.io');
$message = $sender->buildMessageForTest([
    'recipient_email' => 'test@example.test',
    'subject' => 'Reset your ArtsFolio password',
    'body_text' => $payload['body_text'],
    'body_html' => $payload['body_html'],
]);

if (strpos($message, 'multipart/alternative') === false || strpos($message, 'logo_2.png') === false) {
    fwrite(STDERR, "SMTP message does not include multipart HTML logo body
");
    exit(1);
}

echo "Password reset route and email logo static checks passed.
";

// End of file.
