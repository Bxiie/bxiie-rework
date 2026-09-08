<?php

declare(strict_types=1);

namespace App\Tenant\Social;

use RuntimeException;

/**
 * Minimal Instagram API with Instagram Login client.
 *
 * Required permissions are instagram_business_basic and
 * instagram_business_content_publish. Only Creator/Business accounts are
 * supported by Meta's publishing API.
 */
final class InstagramClient
{
    private const GRAPH_BASE = 'https://graph.instagram.com';
    private const OAUTH_AUTHORIZE = 'https://www.instagram.com/oauth/authorize';
    private const OAUTH_TOKEN = 'https://api.instagram.com/oauth/access_token';

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        $clientId = $this->clientId();
        return self::OAUTH_AUTHORIZE . '?' . http_build_query([
            'enable_fb_login' => '0',
            'force_authentication' => '1',
            'client_id' => $clientId,
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
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ], false);
        $shortToken = trim((string) ($short['access_token'] ?? ''));
        if ($shortToken === '') {
            throw new RuntimeException('Instagram did not return an access token.');
        }

        $long = $this->request('GET', self::GRAPH_BASE . '/access_token', [
            'grant_type' => 'ig_exchange_token',
            'client_secret' => $this->clientSecret(),
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

    public function createImageContainer(string $igUserId, string $token, string $imageUrl, bool $carouselItem, ?string $caption = null): string
    {
        $fields = ['image_url' => $imageUrl, 'access_token' => $token];
        if ($carouselItem) {
            $fields['is_carousel_item'] = 'true';
        }
        if ($caption !== null && !$carouselItem) {
            $fields['caption'] = $caption;
        }
        $response = $this->request('POST', self::GRAPH_BASE . '/' . rawurlencode($igUserId) . '/media', [], $fields);
        return $this->requiredId($response, 'Instagram image container');
    }

    /** @param list<string> $children */
    public function createCarouselContainer(string $igUserId, string $token, array $children, string $caption): string
    {
        $response = $this->request('POST', self::GRAPH_BASE . '/' . rawurlencode($igUserId) . '/media', [], [
            'media_type' => 'CAROUSEL',
            'children' => implode(',', $children),
            'caption' => $caption,
            'access_token' => $token,
        ]);
        return $this->requiredId($response, 'Instagram carousel container');
    }

    public function publishContainer(string $igUserId, string $token, string $creationId): string
    {
        $response = $this->request('POST', self::GRAPH_BASE . '/' . rawurlencode($igUserId) . '/media_publish', [], [
            'creation_id' => $creationId,
            'access_token' => $token,
        ]);
        return $this->requiredId($response, 'Instagram published media');
    }

    public function permalink(string $mediaId, string $token): ?string
    {
        $response = $this->request('GET', self::GRAPH_BASE . '/' . rawurlencode($mediaId), [
            'fields' => 'permalink',
            'access_token' => $token,
        ]);
        $permalink = trim((string) ($response['permalink'] ?? ''));
        return $permalink !== '' ? $permalink : null;
    }

    public function publishingLimit(string $igUserId, string $token): ?array
    {
        try {
            return $this->request('GET', self::GRAPH_BASE . '/' . rawurlencode($igUserId) . '/content_publishing_limit', [
                'fields' => 'config,quota_usage',
                'access_token' => $token,
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function clientId(): string
    {
        $value = trim((string) (getenv('ARTSFOLIO_INSTAGRAM_CLIENT_ID') ?: ''));
        if ($value === '') {
            throw new RuntimeException('ARTSFOLIO_INSTAGRAM_CLIENT_ID is not configured.');
        }
        return $value;
    }

    private function clientSecret(): string
    {
        $value = trim((string) (getenv('ARTSFOLIO_INSTAGRAM_CLIENT_SECRET') ?: ''));
        if ($value === '') {
            throw new RuntimeException('ARTSFOLIO_INSTAGRAM_CLIENT_SECRET is not configured.');
        }
        return $value;
    }

    private function requiredId(array $response, string $label): string
    {
        $id = trim((string) ($response['id'] ?? ''));
        if ($id === '') {
            throw new RuntimeException($label . ' ID was not returned.');
        }
        return $id;
    }

    private function request(string $method, string $url, array $query = [], array $form = [], bool $expectJson = true): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for Instagram publishing.');
        }
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }
        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('Unable to initialize Instagram HTTP request.');
        }
        try {
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 45,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_USERAGENT => 'ArtsFolio/1.0 InstagramPublisher',
            ]);
            if (strtoupper($method) === 'POST') {
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($form));
                curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
            }
            $body = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($body === false) {
                throw new RuntimeException('Instagram network request failed: ' . curl_error($curl));
            }
            $decoded = json_decode((string) $body, true);
            if (!is_array($decoded)) {
                if (!$expectJson && $status >= 200 && $status < 300) {
                    return [];
                }
                throw new RuntimeException('Instagram returned an invalid response.');
            }
            if ($status < 200 || $status >= 300 || isset($decoded['error'])) {
                $error = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
                $message = trim((string) ($error['message'] ?? $decoded['error_message'] ?? 'Instagram API request failed.'));
                $code = trim((string) ($error['code'] ?? $decoded['error_type'] ?? $status));
                throw new InstagramApiException($message, $code, $status, $decoded);
            }
            return $decoded;
        } finally {
            curl_close($curl);
        }
    }
}

/** Carries provider-safe diagnostics without exposing access tokens. */
final class InstagramApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $providerCode,
        public readonly int $httpStatus,
        public readonly array $providerResponse,
    ) {
        parent::__construct($message);
    }

    public function authorizationFailure(): bool
    {
        return in_array($this->httpStatus, [401, 403], true) || in_array($this->providerCode, ['190', 'OAuthException'], true);
    }

    public function retryable(): bool
    {
        return $this->httpStatus === 429 || $this->httpStatus >= 500;
    }
}

// End of file.
