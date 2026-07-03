<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/app/Http/Controllers/Tenant/HomeController.php';
$controller = file_get_contents($controllerPath);

$failures = [];

if ($controller === false) {
    $failures[] = 'Could not read HomeController.php.';
} else {
    $syntax = [];
    $exitCode = 0;
    exec('php -l ' . escapeshellarg($controllerPath) . ' 2>&1', $syntax, $exitCode);
    if ($exitCode !== 0) {
        $failures[] = 'HomeController does not pass php -l: ' . implode(' ', $syntax);
    }

    $methodPattern = '/^[ \t]*(public|protected|private)\s+function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s*(?::\s*[^\{\n]+)?\s*\{/m';
    preg_match_all($methodPattern, $controller, $matches, PREG_OFFSET_CAPTURE);

    $homeBody = '';
    $detailBodies = [];
    foreach ($matches[0] as $index => $match) {
        $name = $matches[2][$index][0];
        $start = $match[1];
        $open = strpos($controller, '{', $start);
        if ($open === false) {
            continue;
        }
        $depth = 0;
        $end = null;
        $length = strlen($controller);
        for ($i = $open; $i < $length; $i++) {
            $char = $controller[$i];
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }
        if ($end === null) {
            continue;
        }
        $body = substr($controller, $open + 1, $end - $open - 1);
        if ($name === 'home') {
            $homeBody = $body;
        }
        if ($name !== 'home' && strpos($body, '$artwork') !== false && (strpos($body, 'artworkSalesPanel(') !== false || stripos($name, 'artwork') !== false || strpos($body, 'artwork-detail') !== false)) {
            $detailBodies[] = $body;
        }
    }

    if ($homeBody !== '' && strpos($homeBody, 'artworkNotesHtml($artwork)') !== false) {
        $failures[] = 'HomeController::home() must not render artwork notes because it has no detail-page $artwork row.';
    }

    $detailRendersNotes = false;
    foreach ($detailBodies as $body) {
        if (strpos($body, 'artworkNotesHtml($artwork)') !== false) {
            $detailRendersNotes = true;
            break;
        }
    }
    if (!$detailRendersNotes) {
        $failures[] = 'Artwork detail method should render artworkNotesHtml().';
    }

    if (strpos($controller, 'function artworkNotesHtml(array $artwork): string') === false) {
        $failures[] = 'HomeController must include artworkNotesHtml(array $artwork): string.';
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Artwork notes home-scope static checks failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "Artwork notes home-scope static checks passed.\n";
