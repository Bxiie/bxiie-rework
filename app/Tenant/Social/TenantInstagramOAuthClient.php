<?php

declare(strict_types=1);

namespace App\Tenant\Social;

use RuntimeException;

/**
 * Instagram OAuth client bound to one tenant-owned Meta app.
 *
 * Publishing requests continue to use InstagramClient because they need only
 * the encrypted access token. This class deliberately requires the tenant's
 * App ID and App Secret at construction time so OAuth never falls back to
 * process-wide Meta credentials.
 */
final class TenantInstagramOAuthClient
{
    private const GRAPH_BASE = 'https://graph.instagram.com';
    private const OAUTH_AUTHORIZE = 'https://www.instagram.com/oauth/authorize';
    private const OAUTH_TOKEN = 'https://api.instagram.com/oauth/access_token';

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
        if (trim($this->clientId) === '') {
            throw new RuntimeException('Tenant Meta App ID is not configured.');
        }
        if (trim($this->clientSecret) === '') {
            throw new RuntimeException('Tenant Meta App Secret is not configured.');
        }
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        return self::OAUTH_AUTHORIZE . '?' . http_build_query([
            'enable_fb_login' => '0',
            'force_authentication' => '1',
            'client_id' => trim($this->clientId),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'instagram_business_basic,instagram_business_content_publish',
            'state' => $state,
        ]);
    }

    /** @return array{access_token:string,user_id:string,username:string,expires_at:?string} */
    public function exchangeCode(string $code, string $redirectUri): array
    {
        $short = $this->request('POST', self::OAUTH_TOKEN, [], [
            'client_id' => trim($this->clientId),
            'client_secret' => trim($this->clientSecret),
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);
        $shortToken = trim((string) ($short['access_token'] ?? ''));
        if ($shortToken === '') {
            throw new RuntimeException('Instagram did not return an access token.');
        }

        $long = $this->request('GET', self::GRAPH_BASE . '/access_token', [
            'grant_type' => 'ig_exchange_token',
            'client_secret' => trim($this->clientSecret),
            'access_token' => $shortToken,
        ]);
        $token = trim((string) ($long['access_token'] ?? $shortToken));
        $expiresIn = max(0, (int) ($long['expires_in'] ?? 0));
        $me = $this->request('GET', self::GRAPH_BASE . '/me', [
            'fields' => 'user_id,username',
            'access_token' => $token,
        ]);
        $userId = trim((string) ($me['user_id'] ?? $me['id'] ?? ''));
        if ($userId === '') {
            throw new RuntimeException('Instagram account identity could not be resolved.');
        }

        return [
            'access_token' => $token,
            'user_id' => $userId,
            'username' => trim((string) ($me['username'] ?? '')),
            'expires_at' => $expiresIn > 0 ? gmdate('Y-m-d H:i:s', time() + $expiresIn) : null,
        ];
    }

    private function request(string $method, string $url, array $query = [], array $form = []): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for Instagram authorization.');
        }
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }
        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('Unable to initialize Instagram OAuth request.');
        }
        try {
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 45,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_USERAGENT => 'ArtsFolio/1.0 InstagramOAuth',
            ]);
            if (strtoupper($method) === 'POST') {
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($form));
                curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
            }
            $body = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($body === false) {
                throw new RuntimeException('Instagram OAuth network request failed: ' . curl_error($curl));
            }
            $decoded = json_decode((string) $body, true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Instagram returned an invalid OAuth response.');
            }
            if ($status < 200 || $status >= 300 || isset($decoded['error'])) {
                $error = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
                $message = trim((string) ($error['message'] ?? $decoded['error_message'] ?? 'Instagram OAuth request failed.'));
                throw new RuntimeException($message !== '' ? $message : 'Instagram OAuth request failed.');
            }
            return $decoded;
        } finally {
            curl_close($curl);
        }
    }
}

// End of file.
