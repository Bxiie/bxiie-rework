<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$home = file_get_contents($root . '/app/Http/Controllers/Tenant/HomeController.php');
if ($home === false) {
    fwrite(STDERR, "Unable to read HomeController.php
");
    exit(1);
}

function method_body(string $source, string $method): string
{
    if (!preg_match('/^[ \t]*(?:public|private|protected)\s+function\s+' . preg_quote($method, '/') . '\s*\(/m', $source, $m, PREG_OFFSET_CAPTURE)) {
        return '';
    }
    $start = $m[0][1];
    $brace = strpos($source, '{', $start);
    if ($brace === false) {
        return '';
    }
    $depth = 0;
    $length = strlen($source);
    for ($i = $brace; $i < $length; $i++) {
        $ch = $source[$i];
        if ($ch === '{') {
            $depth++;
        } elseif ($ch === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($source, $start, $i - $start + 1);
            }
        }
    }
    return '';
}

$artwork = method_body($home, 'artwork');
$homeMethod = method_body($home, 'home');
$problems = [];

if ($artwork === '') {
    $problems[] = 'HomeController::artwork() could not be parsed.';
}

$needle = '$this->artworkNotesHtml($artwork)';
if ($artwork !== '' && strpos($artwork, $needle) === false) {
    $problems[] = 'Artwork detail method should render artworkNotesHtml().';
}

if ($artwork !== '') {
    $callPos = strpos($artwork, $needle);
    $assignPos = strpos($artwork, '$artwork =');
    if ($callPos !== false && $assignPos !== false && $callPos < $assignPos) {
        $problems[] = 'Artwork notes render call appears before $artwork is assigned.';
    }
}

if ($homeMethod !== '' && strpos($homeMethod, $needle) !== false) {
    $problems[] = 'Tenant home method must not render artworkNotesHtml($artwork).';
}

if ($problems !== []) {
    fwrite(STDERR, "Artwork notes detail-scope static checks failed:
 - " . implode("
 - ", $problems) . "
");
    exit(1);
}

echo "Artwork notes detail-scope static checks passed.
";
