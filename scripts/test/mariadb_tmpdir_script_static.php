<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$script = file_get_contents($root . '/scripts/ops/configure_mariadb_tmpdir.sh');
foreach ([
    'set -euo pipefail',
    '/var/lib/mysql-tmp',
    '/etc/mysql/mariadb.conf.d/60-artsfolio-tmpdir.cnf',
    'install --directory --owner=mysql --group=mysql --mode=0750',
    'systemctl restart mariadb',
    "SHOW VARIABLES LIKE 'tmpdir'",
    '# End of file.',
] as $fragment) {
    if (!str_contains((string) $script, $fragment)) {
        fwrite(STDERR, "Missing MariaDB tmpdir script fragment: {$fragment}\n");
        exit(1);
    }
}

echo "MariaDB tmpdir script static checks passed.\n";

// End of file.
