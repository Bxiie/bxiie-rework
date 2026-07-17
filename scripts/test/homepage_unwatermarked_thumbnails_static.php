<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$homePath = $root . '/app/Http/Controllers/Tenant/HomeController.php';

if (!is_file($homePath)) {
    fwrite(STDERR, "[FAIL] Missing HomeController.php.\n");
    exit(1);
}

$source = (string) file_get_contents($homePath);
$errors = [];

$homeStart = strpos(
    $source,
    'public function home(Request $request, TenantContext $tenant): Response'
);
$portfolioStart = strpos(
    $source,
    'public function portfolio(Request $request, TenantContext $tenant): Response'
);

if (
    $homeStart === false
    || $portfolioStart === false
    || $portfolioStart <= $homeStart
) {
    $errors[] = 'Unable to isolate the home() method.';
} else {
    $homeMethod = substr(
        $source,
        $homeStart,
        $portfolioStart - $homeStart
    );

    $thumbMarker = ". '&variant=thumb'";
    $commentMarker =
        'Home-page cards always use the unwatermarked thumbnail derivative.';

    if (!str_contains($homeMethod, $thumbMarker)) {
        $errors[] =
            'Home-page artwork cards do not request the thumb derivative.';
    }

    if (!str_contains($homeMethod, $commentMarker)) {
        $errors[] =
            'Missing explicit unwatermarked-thumbnail behavior marker.';
    }

    $mediaAssignmentStart = strpos(
        $homeMethod,
        "\$src = '/media?uuid='"
    );

    if ($mediaAssignmentStart === false) {
        $errors[] = 'Could not locate the home-page media URL assignment.';
    } else {
        $mediaAssignmentEnd = strpos(
            $homeMethod,
            ';',
            $mediaAssignmentStart
        );

        if ($mediaAssignmentEnd === false) {
            $errors[] =
                'Could not isolate the home-page media URL assignment.';
        } else {
            $mediaAssignment = substr(
                $homeMethod,
                $mediaAssignmentStart,
                $mediaAssignmentEnd - $mediaAssignmentStart + 1
            );

            if (!str_contains($mediaAssignment, 'variant=thumb')) {
                $errors[] =
                    'The home-page media URL assignment omits variant=thumb.';
            }
        }
    }
}

if ($errors !== []) {
    fwrite(
        STDERR,
        "[FAIL] Home page unwatermarked-thumbnail static check failed:\n"
    );

    foreach ($errors as $error) {
        fwrite(STDERR, "[FAIL]  - {$error}\n");
    }

    exit(1);
}

echo "[PASS] Home page unwatermarked-thumbnail static check passed.\n";

// End of file.
