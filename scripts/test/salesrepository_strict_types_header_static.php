<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$file = $root . '/app/Tenant/Sales/SalesRepository.php';
$src = file_get_contents($file);
$errors = [];

if ($src === false) {
    $errors[] = 'Could not read SalesRepository.php.';
} else {
    if (!str_starts_with($src, "<?php

declare(strict_types=1);")) {
        $errors[] = 'SalesRepository.php must start with <?php followed immediately by declare(strict_types=1).';
    }

    $beforePhp = substr($src, 0, strpos($src, '<?php') ?: 0);
    if ($beforePhp !== '') {
        $errors[] = 'SalesRepository.php has output before <?php.';
    }
}

$cmd = 'php -l ' . escapeshellarg($file) . ' 2>&1';
$output = [];
$exitCode = 0;
exec($cmd, $output, $exitCode);
if ($exitCode !== 0) {
    $errors[] = 'SalesRepository.php does not pass php -l: ' . implode(' ', $output);
}

if ($errors !== []) {
    fwrite(STDERR, "SalesRepository strict_types header static checks failed:
 - " . implode("
 - ", $errors) . "
");
    exit(1);
}

echo "SalesRepository strict_types header static checks passed.
";
