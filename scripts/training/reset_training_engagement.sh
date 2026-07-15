#!/bin/bash

# Remove and recreate the fictional engagement fixtures for the training tenant.

set -euo pipefail

ROOT_DIR="${ARTSFOLIO_ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
ENV_FILE="${ARTSFOLIO_ENV_FILE:-}"
ROLLBACK_SQL="${ROOT_DIR}/scripts/training/rollback_training_engagement.sql"
APPLY_SCRIPT="${ROOT_DIR}/scripts/training/apply_training_engagement.sh"
DB_HELPER="${ROOT_DIR}/scripts/training/db_client.sh"

if [[ ! -x "${APPLY_SCRIPT}" || ! -f "${ROLLBACK_SQL}" || ! -f "${DB_HELPER}" ]]; then
    printf '[FAIL] Training fixture scripts are incomplete under %s/scripts/training.\n' "${ROOT_DIR}" >&2
    exit 1
fi

if [[ -n "${ENV_FILE}" ]]; then
    [[ "${ENV_FILE}" = /* ]] || ENV_FILE="${ROOT_DIR}/${ENV_FILE}"
    if [[ ! -r "${ENV_FILE}" ]]; then
        printf '[FAIL] Environment file is not readable: %s\n' "${ENV_FILE}" >&2
        exit 1
    fi
    set -a
    # shellcheck disable=SC1090
    . "${ENV_FILE}"
    set +a
elif [[ -r "${ROOT_DIR}/.env" ]]; then
    set -a
    # shellcheck disable=SC1091
    . "${ROOT_DIR}/.env"
    set +a
elif [[ -r /etc/artsfolio/artsfolio.env ]]; then
    set -a
    # shellcheck disable=SC1091
    . /etc/artsfolio/artsfolio.env
    set +a
fi

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_DATABASE:-${DB_NAME:-}}"
DB_USER="${DB_USERNAME:-${DB_USER:-}}"
DB_PASS="${DB_PASSWORD:-${DB_PASS:-}}"

if [[ -z "${DB_NAME}" || -z "${DB_USER}" ]]; then
    printf '[FAIL] Database configuration is incomplete.\n' >&2
    exit 1
fi

# shellcheck disable=SC1090
. "${DB_HELPER}"
training_db_detect_client
printf '[PASS] Database client mode: %s.\n' "${ARTSFOLIO_DB_CLIENT_MODE}"
printf '[RUN] Removing existing training engagement fixtures.\n'
training_db_import "${ROLLBACK_SQL}"

printf '[RUN] Recreating training engagement fixtures.\n'
ARTSFOLIO_ROOT="${ROOT_DIR}" ARTSFOLIO_ENV_FILE="${ENV_FILE}" "${APPLY_SCRIPT}"

# End of file.
