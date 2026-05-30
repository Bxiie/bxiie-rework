<?php

declare(strict_types=1);

namespace App\Support\Pagination;

/**
 * Small helper for page/limit query handling.
 */
final class Pagination
{
    public static function pageFromQuery(mixed $value): int
    {
        $page = (int) ($value ?? 1);

        return max(1, $page);
    }

    public static function limitFromQuery(mixed $value, int $default = 50, int $max = 200): int
    {
        $limit = (int) ($value ?? $default);

        if ($limit <= 0) {
            return $default;
        }

        return min($limit, $max);
    }

    public static function offset(int $page, int $limit): int
    {
        return max(0, ($page - 1) * $limit);
    }

    public static function nextPageUrl(string $path, array $query, int $page): string
    {
        $query['page'] = $page + 1;

        return $path . '?' . http_build_query($query);
    }

    public static function previousPageUrl(string $path, array $query, int $page): ?string
    {
        if ($page <= 1) {
            return null;
        }

        $query['page'] = $page - 1;

        return $path . '?' . http_build_query($query);
    }
}

// End of file.
