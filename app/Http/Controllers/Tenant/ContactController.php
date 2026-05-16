<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Request;
use App\Http\Response;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Contact\ContactMessageService;

/**
 * Handles tenant public contact message submissions.
 */
final class ContactController
{
    public function __construct(
        private readonly ContactMessageService $messages,
        private readonly CsrfTokenService $csrf,
    ) {
    }

    public function submit(Request $request, TenantContext $tenant): Response
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }

        $senderName = trim((string) ($_POST['name'] ?? ''));
        $senderEmail = trim((string) ($_POST['email'] ?? ''));
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));

        if ($senderName === '' || $senderEmail === '' || $message === '') {
            return Response::html('<h1>Missing required fields</h1>', 422);
        }

        if (!filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            return Response::html('<h1>Invalid email address</h1>', 422);
        }

        $this->messages->receive(
            tenant: $tenant,
            senderName: $senderName,
            senderEmail: $senderEmail,
            message: $message,
            subject: $subject !== '' ? $subject : null,
            ipAddress: $request->server('REMOTE_ADDR'),
            userAgent: $request->server('HTTP_USER_AGENT'),
        );

        return Response::html(<<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Message received</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<h1>Message received</h1>
<p>Thank you. Your message has been sent.</p>
<p><a href="/contact">Back to contact</a></p>
</body>
</html>
HTML);
    }
}

// End of file.
