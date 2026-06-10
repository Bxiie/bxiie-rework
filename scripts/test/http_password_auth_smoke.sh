#!/bin/bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${PROJECT_ROOT}"

php scripts/test/password_auth.php >/dev/null

echo "Password auth backend smoke test passed."

# End of file.
