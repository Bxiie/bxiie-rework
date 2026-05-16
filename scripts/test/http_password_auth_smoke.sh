#!/bin/bash
set -euo pipefail

PROJECT_ROOT="/Users/bxiie/Dropbox/tcdev/artsfolio"
cd "${PROJECT_ROOT}"

php scripts/test/password_auth.php >/dev/null

echo "Password auth backend smoke test passed."

# End of file.
