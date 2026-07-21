<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$path = $root . '/public/assets/artsfol-wordmark.png';

if (!is_file($path)) {
    fwrite(STDERR, "[FAIL] Transparent platform wordmark asset is missing.\n");
    exit(1);
}

$info = @getimagesize($path);
if (!is_array($info) || ($info[2] ?? null) !== IMAGETYPE_PNG) {
    fwrite(STDERR, "[FAIL] Platform wordmark is not a PNG.\n");
    exit(1);
}

$png = file_get_contents($path);
if ($png === false || strlen($png) < 26) {
    fwrite(STDERR, "[FAIL] Platform wordmark could not be read.\n");
    exit(1);
}

// PNG color type byte in IHDR. Values 4 and 6 include alpha.
$colorType = ord($png[25]);
if (!in_array($colorType, [4, 6], true)) {
    fwrite(STDERR, "[FAIL] Platform wordmark does not contain an alpha channel.\n");
    exit(1);
}

fwrite(STDOUT, "[PASS] Platform wordmark is a transparent PNG.\n");
