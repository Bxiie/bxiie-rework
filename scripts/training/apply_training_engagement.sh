#!/bin/bash

# Apply the fictional event, message, and mailing-list fixtures to the training tenant.

set -euo pipefail

ROOT_DIR="${ARTSFOLIO_ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
ENV_FILE="${ARTSFOLIO_ENV_FILE:-}"
TENANT_SLUG="training"
SQL_FILE="${ROOT_DIR}/scripts/training/seed_training_engagement.sql"
DB_HELPER="${ROOT_DIR}/scripts/training/db_client.sh"

if [[ ! -f "${SQL_FILE}" || ! -f "${DB_HELPER}" ]]; then
    printf '[FAIL] Training SQL or database helper is missing under %s/scripts/training.\n' "${ROOT_DIR}" >&2
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

tenant_id="$(training_db_exec "SELECT id FROM tenants WHERE slug = '${TENANT_SLUG}' LIMIT 1;" | tail -n 1)"
if [[ -z "${tenant_id}" || ! "${tenant_id}" =~ ^[0-9]+$ ]]; then
    printf '[FAIL] Tenant slug %s was not found in database %s.\n' "${TENANT_SLUG}" "${DB_NAME}" >&2
    exit 1
fi

printf '[RUN] Seeding engagement fixtures for tenant %s (id %s).\n' "${TENANT_SLUG}" "${tenant_id}"
training_db_import "${SQL_FILE}"

counts="$(training_db_exec "
SELECT CONCAT(
    (SELECT COUNT(*) FROM exhibitions WHERE tenant_id = ${tenant_id} AND name IN ('Northstar: Recent Sculpture','Summer Group Exhibition','Open Studio Weekend','Artist Talk: Structure and Balance','Winter Salon','Vermont Sculpture Walk','Museum Collection Acquisition','Proposed Residency')), ' ',
    (SELECT COUNT(*) FROM contact_messages WHERE tenant_id = ${tenant_id} AND sender_email IN ('training-buyer+taylor@example.com','training-curator+jordan@example.com','training-visitor+sam@example.com')), ' ',
    (SELECT COUNT(*) FROM email_signups WHERE tenant_id = ${tenant_id} AND email IN ('training-list+one@example.com','training-list+two@example.com','training-list+pending@example.com','training-list+duplicate@example.com'))
);" | tail -n 1)"
read -r event_count message_count signup_count <<< "${counts}"

if [[ "${event_count}" != "8" || "${message_count}" != "3" || "${signup_count}" != "4" ]]; then
    printf '[FAIL] Verification failed: events=%s messages=%s signups=%s.\n' \
        "${event_count:-?}" "${message_count:-?}" "${signup_count:-?}" >&2
    exit 1
fi

printf '[PASS] Training fixtures verified: 8 events, 3 messages, 4 mailing-list records.\n'

# End of file.
