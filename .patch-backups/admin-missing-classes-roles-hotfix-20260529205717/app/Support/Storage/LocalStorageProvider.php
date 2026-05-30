<?php

declare(strict_types=1);

namespace App\Support\Storage;

use RuntimeException;

/**
 * Local filesystem storage provider.
 */
final class LocalStorageProvider implements StorageInterface
{
    public function __construct(
        private readonly string $rootPath,
    ) {
    }

    public function put(string $path, string $contents): void
    {
        $target = $this->fullPath($path);
        $directory = dirname($target);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create storage directory: {$directory}");
        }

        if (file_put_contents($target, $contents) === false) {
            throw new RuntimeException("Unable to write storage file: {$target}");
        }
    }

    public function get(string $path): string
    {
        $target = $this->fullPath($path);

        if (!is_file($target)) {
            throw new RuntimeException("Storage file not found: {$target}");
        }

        $contents = file_get_contents($target);

        if ($contents === false) {
            throw new RuntimeException("Unable to read storage file: {$target}");
        }

        return $contents;
    }

    public function exists(string $path): bool
    {
        return is_file($this->fullPath($path));
    }

    public function delete(string $path): void
    {
        $target = $this->fullPath($path);

        if (is_file($target) && !unlink($target)) {
            throw new RuntimeException("Unable to delete storage file: {$target}");
        }
    }

    private function fullPath(string $path): string
    {
        $path = ltrim($path, '/');

        if (str_contains($path, '..')) {
            throw new RuntimeException('Storage paths may not contain parent directory traversal.');
        }

        return rtrim($this->rootPath, '/') . '/' . $path;
    }
}

// End of file.
