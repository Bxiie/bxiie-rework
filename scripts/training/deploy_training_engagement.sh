#!/bin/bash
# Deploy the committed training engagement fixtures through ArtsFolio's PHP database configuration.

set -euo pipefail

PROJECT_ROOT="${ARTSFOLIO_ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
ENV_FILE="${ARTSFOLIO_ENV_FILE:-/etc/artsfolio/artsfolio.env}"

if [[ ! -f "${PROJECT_ROOT}/PROJECT_STATE.md" ]]; then
    printf '[FAIL] ArtsFolio repository not found at %s.\n' "${PROJECT_ROOT}" >&2
    exit 1
fi

if [[ ! -r "${ENV_FILE}" ]]; then
    printf '[FAIL] Environment file is not readable: %s\n' "${ENV_FILE}" >&2
    exit 1
fi

printf '[RUN] Deploying training engagement fixtures from commit %s.\n' "$(git -C "${PROJECT_ROOT}" rev-parse --short HEAD 2>/dev/null || printf unknown)"

ARTSFOLIO_ENV_FILE="${ENV_FILE}" \
php "${PROJECT_ROOT}/scripts/training/seed_training_engagement.php"

printf '[PASS] Git-deployed training engagement fixtures are ready.\n'

# End of file.
