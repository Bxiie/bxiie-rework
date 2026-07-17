#!/bin/bash

set -euo pipefail

ARTSFOLIO_ROOT="${ARTSFOLIO_ROOT:-$(cd "$(dirname "$0")/../.." && pwd)}"
ARTSFOLIO_ENV_FILE="${ARTSFOLIO_ENV_FILE:-}"
MODE="${1:---dry-run}"

case "$MODE" in
    --dry-run|--apply)
        ;;
    *)
        printf '[FAIL] Usage: %s [--dry-run|--apply]\n' "$0" >&2
        exit 1
        ;;
esac

export ARTSFOLIO_ROOT
if [[ -n "$ARTSFOLIO_ENV_FILE" ]]; then
    export ARTSFOLIO_ENV_FILE
fi

printf '[RUN] Training artwork metadata mode: %s\n' "$MODE"

php "${ARTSFOLIO_ROOT}/scripts/training/seed_training_artwork_metadata.php" "$MODE"

# End of file.
