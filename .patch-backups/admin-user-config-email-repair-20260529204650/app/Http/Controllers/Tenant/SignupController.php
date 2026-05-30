<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Request;
use App\Http\Response;
use App\Platform\Tenancy\TenantContext;
use App\Platform\Security\RateLimiter;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Signup\EmailSignupService;

/**
 * Handles tenant public email signup submissions.
 */
final class SignupController
{
    public function __construct(
        private readonly EmailSignupService $signups,
        private readonly CsrfTokenService $csrf,
        private readonly ?RateLimiter $rateLimiter = null,
    ) {
    }

    public function submit(Request $request, TenantContext $tenant): Response
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }

        if ($this->rateLimiter && !$this->rateLimiter->allow($this->rateKey($request, $tenant), 5, 300)) {
            return Response::html('<h1>Too many submissions</h1><p>Please wait a few minutes and try again.</p>', 429);
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));

        if ($email === '') {
            return Response::html('<h1>Email is required</h1>', 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return Response::html('<h1>Invalid email address</h1>', 422);
        }

        $this->signups->receive(
            tenant: $tenant,
            email: $email,
            name: $name !== '' ? $name : null,
            source: 'public_site',
            ipAddress: $request->server('REMOTE_ADDR'),
            userAgent: $request->server('HTTP_USER_AGENT'),
        );

        return Response::html(<<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Signup received</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<h1>Signup received</h1>
<p>Thank you. You have been added to the list.</p>
<p><a href="/">Back to site</a></p>
</body>
</html>
HTML);
    }
    private function rateKey(Request $request, TenantContext $tenant): string
    {
        $ip = $request->server('REMOTE_ADDR', 'unknown');

        return 'signup:' . $tenant->tenantId . ':' . $ip;
    }
}

// End of file.
