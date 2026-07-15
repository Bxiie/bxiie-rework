#!/bin/bash

# Provide portable ArtsFolio MariaDB client functions using local clients or Docker.

set -euo pipefail

ARTSFOLIO_DB_CLIENT_MODE=""
ARTSFOLIO_DB_CONTAINER="${ARTSFOLIO_DB_CONTAINER:-artsfolio-mariadb}"

training_db_detect_client() {
    if command -v mysql >/dev/null 2>&1 && command -v mysqldump >/dev/null 2>&1; then
        ARTSFOLIO_DB_CLIENT_MODE="mysql"
        return 0
    fi

    if command -v mariadb >/dev/null 2>&1 && command -v mariadb-dump >/dev/null 2>&1; then
        ARTSFOLIO_DB_CLIENT_MODE="mariadb"
        return 0
    fi

    if command -v docker >/dev/null 2>&1 \
        && docker inspect "${ARTSFOLIO_DB_CONTAINER}" >/dev/null 2>&1 \
        && [[ "$(docker inspect -f '{{.State.Running}}' "${ARTSFOLIO_DB_CONTAINER}" 2>/dev/null)" == "true" ]]; then
        ARTSFOLIO_DB_CLIENT_MODE="docker"
        return 0
    fi

    printf '[FAIL] No usable MariaDB client was found.\n' >&2
    printf '[FAIL] Install mysql/mariadb clients, or start Docker container %s.\n' "${ARTSFOLIO_DB_CONTAINER}" >&2
    return 1
}

training_db_exec() {
    local sql="$1"

    case "${ARTSFOLIO_DB_CLIENT_MODE}" in
        mysql)
            MYSQL_PWD="${DB_PASS}" mysql \
                --host="${DB_HOST}" --port="${DB_PORT}" \
                --user="${DB_USER}" --database="${DB_NAME}" \
                --batch --raw --execute="${sql}"
            ;;
        mariadb)
            MYSQL_PWD="${DB_PASS}" mariadb \
                --host="${DB_HOST}" --port="${DB_PORT}" \
                --user="${DB_USER}" --database="${DB_NAME}" \
                --batch --raw --execute="${sql}"
            ;;
        docker)
            docker exec -i -e MYSQL_PWD="${DB_PASS}" "${ARTSFOLIO_DB_CONTAINER}" \
                mariadb --host=127.0.0.1 --port=3306 \
                --user="${DB_USER}" --database="${DB_NAME}" \
                --batch --raw --execute="${sql}"
            ;;
        *)
            printf '[FAIL] Database client mode has not been initialized.\n' >&2
            return 1
            ;;
    esac
}

training_db_import() {
    local sql_file="$1"

    case "${ARTSFOLIO_DB_CLIENT_MODE}" in
        mysql)
            MYSQL_PWD="${DB_PASS}" mysql \
                --host="${DB_HOST}" --port="${DB_PORT}" \
                --user="${DB_USER}" --database="${DB_NAME}" < "${sql_file}"
            ;;
        mariadb)
            MYSQL_PWD="${DB_PASS}" mariadb \
                --host="${DB_HOST}" --port="${DB_PORT}" \
                --user="${DB_USER}" --database="${DB_NAME}" < "${sql_file}"
            ;;
        docker)
            docker exec -i -e MYSQL_PWD="${DB_PASS}" "${ARTSFOLIO_DB_CONTAINER}" \
                mariadb --host=127.0.0.1 --port=3306 \
                --user="${DB_USER}" --database="${DB_NAME}" < "${sql_file}"
            ;;
        *)
            printf '[FAIL] Database client mode has not been initialized.\n' >&2
            return 1
            ;;
    esac
}

training_db_dump_table() {
    local table_name="$1"
    local where_clause="$2"
    local output_file="$3"

    case "${ARTSFOLIO_DB_CLIENT_MODE}" in
        mysql)
            MYSQL_PWD="${DB_PASS}" mysqldump \
                --host="${DB_HOST}" --port="${DB_PORT}" --user="${DB_USER}" \
                --no-create-info --skip-triggers --single-transaction \
                --where="${where_clause}" "${DB_NAME}" "${table_name}" > "${output_file}"
            ;;
        mariadb)
            MYSQL_PWD="${DB_PASS}" mariadb-dump \
                --host="${DB_HOST}" --port="${DB_PORT}" --user="${DB_USER}" \
                --no-create-info --skip-triggers --single-transaction \
                --where="${where_clause}" "${DB_NAME}" "${table_name}" > "${output_file}"
            ;;
        docker)
            docker exec -i -e MYSQL_PWD="${DB_PASS}" "${ARTSFOLIO_DB_CONTAINER}" \
                mariadb-dump --host=127.0.0.1 --port=3306 --user="${DB_USER}" \
                --no-create-info --skip-triggers --single-transaction \
                --where="${where_clause}" "${DB_NAME}" "${table_name}" > "${output_file}"
            ;;
        *)
            printf '[FAIL] Database client mode has not been initialized.\n' >&2
            return 1
            ;;
    esac
}

# End of file.
