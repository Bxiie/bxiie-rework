#!/bin/bash
set -euo pipefail

BACKUP_DIR="/var/backups/artsfolio"
STAMP="$(date +%Y%m%d-%H%M%S)"
ENV_FILE="${ARTSFOLIO_ENV_FILE:-/etc/artsfolio/artsfolio.env}"

set -a
source "$ENV_FILE"
set +a

mariadb-dump \
  -h "${DB_HOST:-127.0.0.1}" \
  -P "${DB_PORT:-3306}" \
  -u "$DB_USERNAME" \
  -p"$DB_PASSWORD" \
  "$DB_DATABASE" \
  | gzip > "$BACKUP_DIR/artsfolio-db-$STAMP.sql.gz"

find "$BACKUP_DIR" -type f -name 'artsfolio-db-*.sql.gz' -mtime +14 -delete

echo "$BACKUP_DIR/artsfolio-db-$STAMP.sql.gz"
