<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;

/**
 * Handles public platform marketing routes.
 */
final class HomeController
{
    public function home(Request $request): Response
    {
        return Response::html($this->layout(
            title: 'Arts Folio',
            body: <<<HTML
<h1>Arts Folio</h1>
<p>Artist operating platform foundation is alive.</p>
<p>This is the platform marketing surface, not a tenant site.</p>
HTML
        ));
    }

    public function pricing(Request $request): Response
    {
        return Response::html($this->layout(
            title: 'Pricing | Arts Folio',
            body: <<<HTML
<h1>Pricing</h1>
<ul>
    <li>Free: platform subdomain</li>
    <li>Studio: artist site tools</li>
    <li>Professional: custom domain included</li>
    <li>Collective: multi-user and larger portfolio support</li>
</ul>
HTML
        ));
    }

    public function signup(Request $request): Response
    {
        return Response::html($this->layout(
            title: 'Sign up | Arts Folio',
            body: <<<HTML
<h1>Sign up</h1>
<p>Self-service signup will create a global user and tenant workspace.</p>
HTML
        ));
    }

    public function login(Request $request): Response
    {
        return Response::html($this->layout(
            title: 'Login | Arts Folio',
            body: <<<HTML
<h1>Login</h1>
<p>Local email/password, Google OAuth, and Facebook Login will land here.</p>
HTML
        ));
    }

    private function layout(string $title, string $body): string
    {
        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$title}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
{$body}
</body>
</html>
HTML;
    }
}

// End of file.
