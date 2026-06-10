<?php

/**
 * Static guardrails for tenant public chrome.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$tenantControllerDir = $root . '/app/Http/Controllers/Tenant';
$violations = [];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tenantControllerDir));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $relative = substr($path, strlen($root) + 1);
    $contents = file_get_contents($path) ?: '';

    if (str_contains($contents, 'PlatformChrome::platformAdminLink(')) {
        $violations[] = $relative . ' calls PlatformChrome::platformAdminLink(). Tenant chrome must use /admin.';
    }

    if (str_contains($contents, 'tenant-forms.js') && str_contains($contents, 'file_get_contents')) {
        $violations[] = $relative . ' appears to inline tenant-forms.js. Use <script defer src="/assets/tenant-forms.js"></script>.';
    }
}

if ($violations !== []) {
    fwrite(STDERR, implode("\n", $violations) . "\n");
    exit(1);
}

echo "Tenant chrome static checks passed.\n";

// End of file.
