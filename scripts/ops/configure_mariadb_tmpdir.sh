#!/bin/bash

set -euo pipefail

# Configure MariaDB to use disk-backed temporary storage instead of the small
# RAM-backed /tmp mount used by the ArtsFolio production host.
if [[ "${EUID}" -ne 0 ]]; then
    echo "Run this script as root or through sudo." >&2
    exit 1
fi

TMPDIR_PATH="${ARTSFOLIO_MARIADB_TMPDIR:-/var/lib/mysql-tmp}"
CONFIG_PATH="${ARTSFOLIO_MARIADB_TMPDIR_CONFIG:-/etc/mysql/mariadb.conf.d/60-artsfolio-tmpdir.cnf}"

if ! id mysql >/dev/null 2>&1; then
    echo "Missing mysql system user; MariaDB does not appear to be installed." >&2
    exit 1
fi

install --directory --owner=mysql --group=mysql --mode=0750 "${TMPDIR_PATH}"

cat >"${CONFIG_PATH}" <<EOF
[mariadbd]

# Keep analytical temporary files off the small RAM-backed /tmp mount.
tmpdir=${TMPDIR_PATH}

# End of file.
EOF

chown root:root "${CONFIG_PATH}"
chmod 0644 "${CONFIG_PATH}"

if ! mariadbd --verbose --help >/dev/null 2>&1; then
    echo "MariaDB rejected its configuration; inspect ${CONFIG_PATH}." >&2
    exit 1
fi

systemctl restart mariadb
systemctl is-active --quiet mariadb

ACTUAL_TMPDIR="$(mariadb --batch --skip-column-names -e "SHOW VARIABLES LIKE 'tmpdir';" 2>/dev/null | awk '{print $2}')"
if [[ "${ACTUAL_TMPDIR%/}" != "${TMPDIR_PATH%/}" ]]; then
    echo "MariaDB restarted, but tmpdir is '${ACTUAL_TMPDIR}', expected '${TMPDIR_PATH}'." >&2
    exit 1
fi

echo "MariaDB tmpdir configured at ${ACTUAL_TMPDIR}."

# End of file.
