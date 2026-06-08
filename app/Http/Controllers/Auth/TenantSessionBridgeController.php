<?php

/**
 * Cross-domain tenant browser session bridge controller.
 */

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Middleware\CurrentUser;
use App\Http\Request;
use App\Http\Response;
use App\Http\Support\SessionCookie;
use App\Platform\Auth\Session\SessionBridgeRepository;
use App\Platform\Auth\Session\SessionRepository;
use App\Platform\Auth\Session\SessionTokenService;
use App\Platform\Tenancy\TenantContext;

/**
 * Issues a short-lived one-time ticket on one tenant-owned host and
 * consumes it on another tenant-owned host to set a host-local admin cookie.
 */
final class TenantSessionBridgeController
{
    public function __construct(
        private readonly SessionBridgeRepository $bridges,
        private readonly SessionRepository $sessions,
        private readonly SessionTokenService $tokens,
    ) {
    }

    public function bridge(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$currentUser || empty($currentUser['user_id'])) {
            return new Response('', 303, ['Location' => '/login?return_to=' . rawurlencode($request->path())]);
        }

        $returnUrl = (string) ($request->query('return_to', '') ?? '');
        $returnHost = strtolower((string) parse_url($returnUrl, PHP_URL_HOST));

        if (!$this->isHttpsUrl($returnUrl) || !$this->bridges->tenantOwnsHost($tenant->tenantId, $returnHost)) {
            return Response::html('<h1>Invalid session bridge target</h1>', 422);
        }

        $ticket = bin2hex(random_bytes(32));
        $this->bridges->createTicket(
            ticketHash: hash('sha256', $ticket),
            tenantId: $tenant->tenantId,
            userId: (int) $currentUser['user_id'],
            returnUrl: $returnUrl,
        );

        $separator = str_contains($returnUrl, '?') ? '&' : '?';
        return new Response('', 303, ['Location' => $returnUrl . $separator . 'af_session_bridge=' . rawurlencode($ticket)]);
    }

    public function consume(Request $request): Response
    {
        $ticket = (string) ($request->query('af_session_bridge', '') ?? '');

        if ($ticket === '') {
            return Response::html('<h1>Missing session bridge ticket</h1>', 422);
        }

        $bridge = $this->bridges->consumeTicket(hash('sha256', $ticket));
        if (!$bridge) {
            return Response::html('<h1>Expired session bridge ticket</h1><p>Please sign in again.</p>', 403);
        }

        $sessionToken = $this->tokens->generateToken();
        $this->sessions->create(
            sessionHash: $this->tokens->hashToken($sessionToken),
            userId: (int) $bridge['user_id'],
            tenantId: (int) $bridge['tenant_id'],
            ipAddress: $request->server('REMOTE_ADDR'),
            userAgent: $request->server('HTTP_USER_AGENT'),
        );

        $cleanUrl = $this->removeBridgeTicket((string) $bridge['return_url']);

        return new Response('', 303, [
            'Set-Cookie' => SessionCookie::loginHeaders($sessionToken),
            'Location' => $cleanUrl,
        ]);
    }

    public function tenantDomainBridgeRedirect(Request $request, TenantContext $tenant): Response
    {
        $host = strtolower(trim(explode(':', $request->host(), 2)[0]));
        $issuerHost = $this->bridges->preferredBridgeIssuerHost($tenant->tenantId, $host);
        if (!$issuerHost) {
            return new Response('', 303, ['Location' => '/login?return_to=' . rawurlencode($request->path())]);
        }

        $scheme = $this->isSecureRequest($request) ? 'https' : 'http';
        $returnUrl = $scheme . '://' . $host . $request->path();
        $query = $_GET;
        unset($query['af_session_bridge']);
        if ($query) {
            $returnUrl .= '?' . http_build_query($query);
        }

        $issuer = 'https://' . $issuerHost . '/auth/tenant-session/bridge?return_to=' . rawurlencode($returnUrl);
        return new Response('', 303, ['Location' => $issuer]);
    }

    private function isHttpsUrl(string $url): bool
    {
        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && (string) parse_url($url, PHP_URL_HOST) !== '';
    }

    private function removeBridgeTicket(string $url): string
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return '/admin';
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        unset($query['af_session_bridge']);

        $clean = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['path'])) {
            $clean .= $parts['path'];
        }
        if ($query) {
            $clean .= '?' . http_build_query($query);
        }

        return $clean;
    }

    private function isSecureRequest(Request $request): bool
    {
        return strtolower((string) $request->server('HTTP_X_FORWARDED_PROTO', '')) === 'https'
            || strtolower((string) $request->server('HTTPS', '')) === 'on'
            || (string) $request->server('HTTPS', '') === '1';
    }
}

// End of file.
