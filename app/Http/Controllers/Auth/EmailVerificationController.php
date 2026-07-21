<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Request;
use App\Http\Response;
use App\Platform\Auth\Email\EmailVerificationService;
use RuntimeException;

final class EmailVerificationController
{
    public function __construct(private readonly EmailVerificationService $verification)
    {
    }

    public function verify(Request $request): Response
    {
        $token = trim((string) $request->query('token', ''));
        if ($token === '') {
            return $this->page('Verification link incomplete', 'This verification link is missing its token. Request a new verification email and try again.', 400);
        }

        try {
            $this->verification->verifyEmail($token);
        } catch (RuntimeException $e) {
            return $this->page('Verification link unavailable', 'This verification link is invalid, expired, or has already been used. Request a new verification email if your address is still unverified.', 400);
        }

        return $this->page('Email verified', 'Your email address has been verified. You may now sign in to ArtsFolio.', 200, '/login', 'Sign in');
    }

    private function page(string $title, string $message, int $status, string $href = '/', string $label = 'Return to ArtsFolio'): Response
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeHref = htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeLabel = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return Response::html('<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $safeTitle . ' | ArtsFolio</title><style>body{margin:0;background:#f7f4ee;color:#201d18;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.55}main{max-width:680px;margin:10vh auto;padding:24px}.card{background:#fff;border:1px solid #ded6c8;border-radius:18px;padding:32px;box-shadow:0 14px 40px rgb(0 0 0/.07)}h1{margin-top:0}a{display:inline-block;margin-top:14px;padding:10px 16px;border-radius:999px;background:#1f5f5b;color:#fff;text-decoration:none;font-weight:700}</style></head><body><main><section class="card"><h1>' . $safeTitle . '</h1><p>' . $safeMessage . '</p><a href="' . $safeHref . '">' . $safeLabel . '</a></section></main></body></html>', $status);
    }
}

// End of file.
