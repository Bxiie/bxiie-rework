<?php

declare(strict_types=1);

namespace App\Support\Storage;

/**
 * Defines the storage contract used by tenant media systems.
 */
interface StorageInterface
{
    public function put(string $path, string $contents): void;

    public function get(string $path): string;

    public function exists(string $path): bool;

    public function delete(string $path): void;
}

// End of file.
