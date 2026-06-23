<?php
declare(strict_types=1);

/**
 * Regression checks for platform help copyright rendering.
 */

$projectRoot = dirname(__DIR__, 2);
$controllerPath = $projectRoot . '/app/Http/Controllers/Platform/HelpController.php';

$controllerContents = file_get_contents($controllerPath);

if ($controllerContents === false) {
    fwrite(STDERR, "Platform help copyright static check failed: unable to read HelpController.php.\n");
    exit(1);
}

$failures = [];

foreach ([
    '$platformCopyright = \App\Http\View\PlatformChrome::copyrightLine();',
    '<footer class="platform-footer"><span>{$platformCopyright}</span>',
] as $requiredText) {
    if (!str_contains($controllerContents, $requiredText)) {
        $failures[] = "HelpController.php missing: {$requiredText}";
    }
}

if (str_contains(
    $controllerContents,
    '{\App\Http\View\PlatformChrome::copyrightLine()}'
)) {
    $failures[] = 'HelpController.php still emits the copyright method call literally.';
}

if ($failures !== []) {
    fwrite(
        STDERR,
        "Platform help copyright static check failed:\n - "
        . implode("\n - ", $failures)
        . "\n"
    );
    exit(1);
}

fwrite(STDOUT, "Platform help copyright static checks passed.\n");

/* End of file. */