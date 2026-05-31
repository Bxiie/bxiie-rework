#!/bin/bash
set -euo pipefail

assert_redirect_location() {
  local description="$1"
  local host="$2"
  local path="$3"
  local expected_location="$4"
  local location

  location="$(curl -fsSI -H "Host: ${host}" "http://127.0.0.1:18080${path}" | awk 'BEGIN{IGNORECASE=1} /^location:/ {sub(/\r$/, "", $0); print substr($0, 11); exit}')"
  if [[ "${location}" != "${expected_location}" ]]; then
    echo "FAILED: ${description}" >&2
    echo "Expected Location: ${expected_location}" >&2
    echo "Actual Location: ${location}" >&2
    return 1
  fi
}


# HTTP smoke tests for the platform/tenant split.
#
# These tests intentionally avoid brittle scaffold copy. Tenant assertions check
# resolution semantics, expected configured identity, and absence of obvious
# platform-only content.

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${PROJECT_ROOT}"

PORT="${ARTSFOLIO_HTTP_SMOKE_PORT:-18080}"
ENV_FILE="${ARTSFOLIO_ENV_FILE:-.env.local}"
BASE_URL="http://127.0.0.1:${PORT}"
LOG_FILE="/tmp/artsfolio-http-smoke-${PORT}.log"
TENANT_HOST="${ARTSFOLIO_SMOKE_TENANT_HOST:-bxiie.com}"
TENANT_EXPECTED_TITLE="${ARTSFOLIO_SMOKE_TENANT_TITLE:-James Payne Art}"

cleanup() {
  if [[ -n "${SERVER_PID:-}" ]] && kill -0 "${SERVER_PID}" 2>/dev/null; then
    kill "${SERVER_PID}" >/dev/null 2>&1 || true
    wait "${SERVER_PID}" 2>/dev/null || true
  fi
}

trap cleanup EXIT

ARTSFOLIO_ENV_FILE="${ENV_FILE}" php -S "127.0.0.1:${PORT}" -t public public/index.php >"${LOG_FILE}" 2>&1 &
SERVER_PID=$!

for _ in {1..40}; do
  if curl -fsS -H "Host: artsfol.io" "${BASE_URL}/" >/dev/null 2>&1; then
    break
  fi

  sleep 0.25
done

fetch_body() {
  local host="$1"
  local path="$2"

  curl -fsS -H "Host: ${host}" "${BASE_URL}${path}"
}

fail_with_body() {
  local description="$1"
  local message="$2"
  local body="$3"

  echo "FAILED: ${description}" >&2
  echo "${message}" >&2
  echo "Response body:" >&2
  echo "${body}" >&2
  echo "Server log:" >&2
  cat "${LOG_FILE}" >&2
  exit 1
}

assert_contains_ci() {
  local description="$1"
  local host="$2"
  local path="$3"
  local expected="$4"

  local body
  body="$(fetch_body "${host}" "${path}")"

  if ! grep -Fqi -- "${expected}" <<<"${body}"; then
    fail_with_body "${description}" "Expected to find, case-insensitive: ${expected}" "${body}"
  fi

  echo "PASS: ${description}"
}

assert_not_contains_ci() {
  local description="$1"
  local host="$2"
  local path="$3"
  local unexpected="$4"

  local body
  body="$(fetch_body "${host}" "${path}")"

  if grep -Fqi -- "${unexpected}" <<<"${body}"; then
    fail_with_body "${description}" "Did not expect to find, case-insensitive: ${unexpected}" "${body}"
  fi

  echo "PASS: ${description}"
}

assert_status() {
  local description="$1"
  local host="$2"
  local path="$3"
  local expected_status="$4"

  local status
  status="$(curl -sS -o /tmp/artsfolio-http-smoke-body.txt -w "%{http_code}" -H "Host: ${host}" "${BASE_URL}${path}")"

  if [[ "${status}" != "${expected_status}" ]]; then
    echo "FAILED: ${description}" >&2
    echo "Expected status: ${expected_status}" >&2
    echo "Actual status: ${status}" >&2
    cat /tmp/artsfolio-http-smoke-body.txt >&2
    echo "Server log:" >&2
    cat "${LOG_FILE}" >&2
    exit 1
  fi

  echo "PASS: ${description}"
}

assert_tenant_resolves() {
  local host="$1"

  local resolved
  if ! resolved="$(ARTSFOLIO_ENV_FILE="${ENV_FILE}" php scripts/test/resolve_tenant.php "${host}")"; then
    echo "FAILED: tenant resolver" >&2
    echo "Could not resolve tenant host: ${host}" >&2
    exit 1
  fi

  if ! grep -Fq '"slug": "bxiie"' <<<"${resolved}"; then
    echo "FAILED: tenant resolver" >&2
    echo "Expected tenant host ${host} to resolve to slug bxiie." >&2
    echo "Resolver output:" >&2
    echo "${resolved}" >&2
    exit 1
  fi

  echo "PASS: tenant resolver"
}

assert_redirect_location "platform admin tenant-host redirect" "bxiie.artsfol.io" "/platform/admin" "https://artsfol.io/platform/admin"
assert_contains_ci "platform home" "artsfol.io" "/" "ArtsFolio"
assert_contains_ci "platform pricing" "artsfol.io" "/pricing" "Pricing"
assert_contains_ci "platform login form" "artsfol.io" "/login" "login"

assert_tenant_resolves "${TENANT_HOST}"
assert_contains_ci "tenant home configured title" "${TENANT_HOST}" "/" "${TENANT_EXPECTED_TITLE}"
assert_not_contains_ci "tenant home is not platform content" "${TENANT_HOST}" "/" "Artist Operating Platform"
assert_not_contains_ci "tenant home is not platform admin" "${TENANT_HOST}" "/" "Platform Admin"
assert_contains_ci "tenant contact form" "${TENANT_HOST}" "/contact" "Contact"
assert_contains_ci "tenant portfolio" "${TENANT_HOST}" "/portfolio" "Portfolio"

assert_status "tenant API without token returns unauthorized JSON" "${TENANT_HOST}" "/api/me" "401"
assert_status "platform API without token returns unauthorized JSON" "artsfol.io" "/api/me" "401"

echo
echo "HTTP smoke tests passed."

# End of file.

# Tenant public pages must link to the tenant admin on the tenant host, not platform admin.
# This static check prevents the tenant navigation from leaking /platform/admin again.
if grep -R 'href=["'"'"'].*platform/admin' app/Http/Controllers/Tenant >/dev/null 2>&1; then
  echo "FAILED: tenant admin nav points to platform admin" >&2
  grep -R 'href=["'"'"'].*platform/admin' -n app/Http/Controllers/Tenant >&2 || true
  exit 1
fi
echo "tenant admin nav points to tenant admin."
