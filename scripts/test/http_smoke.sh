#!/bin/bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${PROJECT_ROOT}"

PORT="${ARTSFOLIO_HTTP_SMOKE_PORT:-18080}"
ENV_FILE="${ARTSFOLIO_ENV_FILE:-.env.local}"
BASE_URL="http://127.0.0.1:${PORT}"
LOG_FILE="/tmp/artsfolio-http-smoke-${PORT}.log"

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

assert_contains() {
  local description="$1"
  local host="$2"
  local path="$3"
  local expected="$4"

  local body
  body="$(curl -fsS -H "Host: ${host}" "${BASE_URL}${path}")"

  if [[ "${body}" != *"${expected}"* ]]; then
    echo "FAILED: ${description}" >&2
    echo "Expected to find: ${expected}" >&2
    echo "Response body:" >&2
    echo "${body}" >&2
    echo "Server log:" >&2
    cat "${LOG_FILE}" >&2
    exit 1
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

assert_contains "platform home" "artsfol.io" "/" "ArtsFolio"
assert_contains "platform pricing" "artsfol.io" "/pricing" "Pricing"
assert_contains "platform login form" "artsfol.io" "/login" "login"

assert_contains "tenant home" "bxiie.com" "/" "James Payne Art"
assert_contains "tenant contact form" "bxiie.com" "/contact" "Contact"
assert_contains "tenant portfolio" "bxiie.com" "/portfolio" "Portfolio"

assert_status "tenant API without token returns unauthorized JSON" "bxiie.com" "/api/me" "401"
assert_status "platform API without token returns unauthorized JSON" "artsfol.io" "/api/me" "401"

echo
echo "HTTP smoke tests passed."

# End of file.
