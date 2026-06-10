<?php
/**
 * Privacy-conscious usage tracking for admin analytics.
 */

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

final class StatsTracker
{
    public function __construct(private PDO $db)
    {
    }

    public function hit(int $tenantId, string $type, ?int $imageId = null): void
    {
        $ip = $this->requestIp();
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $ref = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 500);
        $path = substr(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', 0, 500);
        $location = $this->locationForIp($ip);

        $stmt = $this->db->prepare(
            'INSERT INTO page_views (
                tenant_id,
                event_type,
                image_id,
                path,
                referrer,
                user_agent,
                ip_hash,
                country_code,
                city,
                state,
                country,
                created_at
            ) VALUES (
                :tenant_id,
                :event_type,
                :image_id,
                :path,
                :referrer,
                :user_agent,
                :ip_hash,
                :country_code,
                :city,
                :state,
                :country,
                datetime("now")
            )'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'event_type' => $type,
            'image_id' => $imageId,
            'path' => $path,
            'referrer' => $ref,
            'user_agent' => $ua,
            'ip_hash' => hash('sha256', $ip . '|bxiie-stats'),
            'country_code' => substr($location['country_code'] ?? '', 0, 16),
            'city' => substr($location['city'] ?? '', 0, 120),
            'state' => substr($location['state'] ?? '', 0, 120),
            'country' => substr($location['country'] ?? '', 0, 120),
        ]);
    }

    private function requestIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            $value = trim((string) ($_SERVER[$key] ?? ''));
            if ($value === '') {
                continue;
            }

            $first = trim(explode(',', $value)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }

        return '';
    }

    private function locationForIp(string $ip): array
    {
        $headerLocation = $this->locationFromHeaders();
        if ($headerLocation['city'] !== '' || $headerLocation['state'] !== '' || $headerLocation['country'] !== '') {
            return $headerLocation;
        }

        if ($ip === '' || $this->isPrivateIp($ip)) {
            return $headerLocation;
        }

        $ipHash = hash('sha256', $ip . '|bxiie-stats');
        $cached = $this->cachedLocation($ipHash);
        if ($cached !== null) {
            return $cached;
        }

        $lookedUp = $this->lookupIpLocation($ip);
        $this->cacheLocation($ipHash, $lookedUp);

        return $lookedUp;
    }

    private function locationFromHeaders(): array
    {
        $countryCode = $this->firstHeader(['HTTP_CF_IPCOUNTRY', 'HTTP_X_GEO_COUNTRY_CODE', 'HTTP_X_APPENGINE_COUNTRY']);
        $country = $this->firstHeader(['HTTP_CF_COUNTRY', 'HTTP_X_GEO_COUNTRY', 'HTTP_X_APPENGINE_COUNTRY']);
        $state = $this->firstHeader(['HTTP_CF_REGION', 'HTTP_CF_REGION_CODE', 'HTTP_X_GEO_STATE', 'HTTP_X_APPENGINE_REGION']);
        $city = $this->firstHeader(['HTTP_CF_IPCITY', 'HTTP_X_GEO_CITY', 'HTTP_X_APPENGINE_CITY']);

        if ($country === '' && $countryCode !== '') {
            $country = $countryCode;
        }

        return [
            'country_code' => $countryCode,
            'city' => $city,
            'state' => $state,
            'country' => $country,
        ];
    }

    private function cachedLocation(string $ipHash): ?array
    {
        try {
            $stmt = $this->db->prepare('SELECT country_code, city, state, country FROM ip_geolocations WHERE ip_hash = :ip_hash');
            $stmt->execute(['ip_hash' => $ipHash]);
            $row = $stmt->fetch();

            return $row ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private function cacheLocation(string $ipHash, array $location): void
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO ip_geolocations (ip_hash, country_code, city, state, country, created_at, updated_at)
                 VALUES (:ip_hash, :country_code, :city, :state, :country, datetime("now"), datetime("now"))
                 ON CONFLICT(ip_hash) DO UPDATE SET
                    country_code = excluded.country_code,
                    city = excluded.city,
                    state = excluded.state,
                    country = excluded.country,
                    updated_at = datetime("now")'
            );
            $stmt->execute([
                'ip_hash' => $ipHash,
                'country_code' => $location['country_code'] ?? '',
                'city' => $location['city'] ?? '',
                'state' => $location['state'] ?? '',
                'country' => $location['country'] ?? '',
            ]);
        } catch (Throwable) {
            // Stats must never break the public site.
        }
    }

    private function lookupIpLocation(string $ip): array
    {
        $empty = ['country_code' => '', 'city' => '', 'state' => '', 'country' => ''];
        $url = 'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,country,countryCode,regionName,city';
        $context = stream_context_create(['http' => ['timeout' => 2]]);
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            return $empty;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || ($decoded['status'] ?? '') !== 'success') {
            return $empty;
        }

        return [
            'country_code' => (string) ($decoded['countryCode'] ?? ''),
            'city' => (string) ($decoded['city'] ?? ''),
            'state' => (string) ($decoded['regionName'] ?? ''),
            'country' => (string) ($decoded['country'] ?? ''),
        ];
    }

    private function isPrivateIp(string $ip): bool
    {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    private function firstHeader(array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($_SERVER[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}

// End of file.
