<?php

declare(strict_types=1);

namespace App\Platform\Analytics;

use App\Http\Request;
use PDO;
use Throwable;

/**
 * Resolves coarse visitor location data for analytics without storing raw IP addresses.
 *
 * Header-provided location data is preferred because it is supplied by trusted edge
 * infrastructure such as Cloudflare, reverse proxies, or a load balancer. When no
 * headers are available, the resolver can perform a short external lookup against
 * ip-api.com and cache the result by the existing analytics IP hash.
 */
final class AnalyticsLocationResolver
{
    private const CACHE_TABLE = 'analytics_ip_locations';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Returns country, region, and city values suitable for analytics_events.
     *
     * The returned array always contains the same keys. Empty strings mean that no
     * location signal was available or the IP address is private/local.
     *
     * @return array{country:string,region:string,city:string,source:string}
     */
    public function resolve(Request $request, string $ipAddress, string $ipHash): array
    {
        $headerLocation = $this->fromHeaders($request);
        if ($this->hasLocation($headerLocation)) {
            $this->cache($ipHash, $headerLocation);
            return $headerLocation;
        }

        $cachedLocation = $this->fromCache($ipHash);
        if ($this->hasLocation($cachedLocation)) {
            return $cachedLocation;
        }

        if (!$this->isPublicIp($ipAddress)) {
            return $this->emptyLocation('private_ip');
        }

        $lookupLocation = $this->fromIpApi($ipAddress);
        if ($this->hasLocation($lookupLocation)) {
            $this->cache($ipHash, $lookupLocation);
        }

        return $lookupLocation;
    }

    /** @return array{country:string,region:string,city:string,source:string} */
    private function fromHeaders(Request $request): array
    {
        $country = $this->firstHeader($request, [
            'HTTP_CF_COUNTRY',
            'HTTP_X_GEO_COUNTRY',
            'HTTP_X_APPENGINE_COUNTRY',
            'HTTP_X_FORWARDED_COUNTRY',
        ]);

        $countryCode = $this->firstHeader($request, [
            'HTTP_CF_IPCOUNTRY',
            'HTTP_X_GEO_COUNTRY_CODE',
            'HTTP_X_APPENGINE_COUNTRY_CODE',
        ]);

        $region = $this->firstHeader($request, [
            'HTTP_CF_REGION',
            'HTTP_X_GEO_REGION',
            'HTTP_X_GEO_STATE',
            'HTTP_X_APPENGINE_REGION',
        ]);

        $city = $this->firstHeader($request, [
            'HTTP_CF_IPCITY',
            'HTTP_CF_CITY',
            'HTTP_X_GEO_CITY',
            'HTTP_X_APPENGINE_CITY',
        ]);

        return [
            'country' => $this->limit($country !== '' ? $country : $countryCode, 120),
            'region' => $this->limit($region, 120),
            'city' => $this->limit($city, 120),
            'source' => 'headers',
        ];
    }

    /** @param list<string> $names */
    private function firstHeader(Request $request, array $names): string
    {
        foreach ($names as $name) {
            $value = trim((string) $request->server($name, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /** @return array{country:string,region:string,city:string,source:string} */
    private function fromCache(string $ipHash): array
    {
        try {
            if (!$this->tableExists(self::CACHE_TABLE)) {
                return $this->emptyLocation('cache_missing');
            }

            $stmt = $this->pdo->prepare(
                'SELECT country, region, city, source
                   FROM analytics_ip_locations
                  WHERE ip_hash = :ip_hash'
            );
            $stmt->execute(['ip_hash' => $ipHash]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return $this->emptyLocation('cache_miss');
            }

            return [
                'country' => (string) ($row['country'] ?? ''),
                'region' => (string) ($row['region'] ?? ''),
                'city' => (string) ($row['city'] ?? ''),
                'source' => (string) ($row['source'] ?? 'cache'),
            ];
        } catch (Throwable) {
            return $this->emptyLocation('cache_error');
        }
    }

    /** @param array{country:string,region:string,city:string,source:string} $location */
    private function cache(string $ipHash, array $location): void
    {
        try {
            if (!$this->tableExists(self::CACHE_TABLE)) {
                return;
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO analytics_ip_locations (
                    ip_hash,
                    country,
                    region,
                    city,
                    source,
                    created_at,
                    updated_at
                ) VALUES (
                    :ip_hash,
                    :country,
                    :region,
                    :city,
                    :source,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                ) ON DUPLICATE KEY UPDATE
                    country = VALUES(country),
                    region = VALUES(region),
                    city = VALUES(city),
                    source = VALUES(source),
                    updated_at = CURRENT_TIMESTAMP'
            );
            $stmt->execute([
                'ip_hash' => $ipHash,
                'country' => $this->limit($location['country'], 120),
                'region' => $this->limit($location['region'], 120),
                'city' => $this->limit($location['city'], 120),
                'source' => $this->limit($location['source'], 40),
            ]);
        } catch (Throwable) {
            // Location cache failures must never break analytics or public pages.
        }
    }

    /** @return array{country:string,region:string,city:string,source:string} */
    private function fromIpApi(string $ipAddress): array
    {
        try {
            $url = 'http://ip-api.com/json/' . rawurlencode($ipAddress) . '?fields=status,country,regionName,city';
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 1.5,
                    'ignore_errors' => true,
                    'header' => "User-Agent: ArtsFolio analytics location resolver\r\n",
                ],
            ]);

            $json = @file_get_contents($url, false, $context);
            if (!is_string($json) || $json === '') {
                return $this->emptyLocation('ip_api_empty');
            }

            $decoded = json_decode($json, true);
            if (!is_array($decoded) || ($decoded['status'] ?? '') !== 'success') {
                return $this->emptyLocation('ip_api_no_match');
            }

            return [
                'country' => $this->limit((string) ($decoded['country'] ?? ''), 120),
                'region' => $this->limit((string) ($decoded['regionName'] ?? ''), 120),
                'city' => $this->limit((string) ($decoded['city'] ?? ''), 120),
                'source' => 'ip_api',
            ];
        } catch (Throwable) {
            return $this->emptyLocation('ip_api_error');
        }
    }

    private function isPublicIp(string $ipAddress): bool
    {
        return filter_var(
            $ipAddress,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    /** @param array{country:string,region:string,city:string,source:string} $location */
    private function hasLocation(array $location): bool
    {
        return $location['country'] !== '' || $location['region'] !== '' || $location['city'] !== '';
    }

    /** @return array{country:string,region:string,city:string,source:string} */
    private function emptyLocation(string $source): array
    {
        return ['country' => '', 'region' => '', 'city' => '', 'source' => $source];
    }

    private function tableExists(string $tableName): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
               FROM information_schema.tables
              WHERE table_schema = DATABASE()
                AND table_name = :table_name'
        );
        $stmt->execute(['table_name' => $tableName]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function limit(string $value, int $length): string
    {
        return mb_substr(trim($value), 0, $length);
    }
}

// End of file.
