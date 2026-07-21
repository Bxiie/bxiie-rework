#!/bin/bash
#
# Create a compact, source-complete ArtsFolio bundle for review in a new
# ChatGPT conversation.
#
# The bundle includes current source code, migrations, tests, scripts,
# documentation, configuration examples, templates, and public assets. It
# excludes runtime data, uploads, secrets, caches, generated dependencies,
# retained patch backups, compressed archives, and macOS metadata.
#

set -Eeuo pipefail
IFS=$'\n\t'

printf '[INFO] Running...\n'

CREATE_CHECKSUM=false

case "${1:-}" in
    "")
        ;;
    --checksum)
        CREATE_CHECKSUM=true
        ;;
    -h|--help)
        cat <<'EOF'
Usage: bundle_artsfolio_for_ai.sh [--checksum]

Create a compact ArtsFolio source bundle for use in a new AI conversation.

Options:
  --checksum  Also create and display a SHA-256 checksum sidecar.
  -h, --help  Show this help text.
EOF
        exit 0
        ;;
    *)
        printf '[FAIL] Unknown argument: %s\n' "$1" >&2
        printf 'Usage: bundle_artsfolio_for_ai.sh [--checksum]\n' >&2
        exit 2
        ;;
esac

if (( $# > 1 )); then
    printf '[FAIL] Too many arguments.\n' >&2
    printf 'Usage: bundle_artsfolio_for_ai.sh [--checksum]\n' >&2
    exit 2
fi

readonly PROJECT_ROOT="/Users/bxiie/Dropbox/tcdev/artsfolio"
readonly BUNDLE_DIR="${PROJECT_ROOT}/bundle"
readonly TIMESTAMP="$(date +%Y%m%d%H%M%S)"
readonly OUTPUT_FILE="${BUNDLE_DIR}/artsfolio_ai_context_${TIMESTAMP}.tar.gz"
readonly CHECKSUM_FILE="${OUTPUT_FILE}.sha256"
readonly CONTEXT_DIR="${PROJECT_ROOT}/.artsfolio_bundle_context_${TIMESTAMP}"

cleanup() {
    # Remove only the temporary context directory created by this run.
    rm -rf -- "${CONTEXT_DIR}"
}

fail() {
    printf '[FAIL] %s\n' "$*" >&2
    exit 1
}

trap cleanup EXIT INT TERM

[[ -d "${PROJECT_ROOT}" ]] || fail "Project root does not exist: ${PROJECT_ROOT}"
[[ -f "${PROJECT_ROOT}/PROJECT_STATE.md" ]] || fail "PROJECT_STATE.md is missing from ${PROJECT_ROOT}"
[[ -f "${PROJECT_ROOT}/composer.json" ]] || fail "composer.json is missing from ${PROJECT_ROOT}"
command -v tar >/dev/null 2>&1 || fail "tar is not installed"
command -v find >/dev/null 2>&1 || fail "find is not installed"
command -v git >/dev/null 2>&1 || fail "git is not installed"

mkdir -p -- "${BUNDLE_DIR}"
mkdir -p -- "${CONTEXT_DIR}"

cat > "${CONTEXT_DIR}/README.txt" <<EOF
ArtsFolio AI review bundle
==========================

Generated: $(date -u '+%Y-%m-%dT%H:%M:%SZ')
Source: ${PROJECT_ROOT}
Bundle: $(basename "${OUTPUT_FILE}")

Purpose
-------
This archive is intended to provide a new ChatGPT conversation with the
current ArtsFolio source tree and enough metadata to understand the working
copy quickly.

Included
--------
- Application source code
- Public entry points and maintained assets
- Database migrations and seed/import definitions
- Automated tests and fixtures
- Deployment, maintenance, and operational scripts
- Developer, administrator, and user documentation
- Templates
- Composer manifests and lockfiles
- Current PROJECT_STATE.md and README files
- Current tracked and untracked source files that are not excluded below

Excluded
--------
- Git object database
- Runtime storage, uploads, logs, caches, sessions, queues, and generated data
- Bundle output and prior compressed archives
- ArtsFolio video factory source and generated training-video assets
- Patch/update backups and editor backup files
- Composer vendor and Node dependency trees
- Secrets, environment files, certificates, and private keys
- macOS AppleDouble files and .DS_Store files
- Test coverage, IDE metadata, and temporary directories

Important
---------
The bundle captures the current working-tree files, including uncommitted
source changes. See git_status.txt and git_head.txt in this directory.
EOF

{
    printf 'Generated UTC: %s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
    printf 'Project root: %s\n' "${PROJECT_ROOT}"
    printf 'Git repository: '
    if git -C "${PROJECT_ROOT}" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
        printf 'yes\n'
        printf 'Branch: %s\n' "$(git -C "${PROJECT_ROOT}" branch --show-current 2>/dev/null || true)"
        printf 'Commit: %s\n' "$(git -C "${PROJECT_ROOT}" rev-parse HEAD 2>/dev/null || true)"
        printf 'Describe: %s\n' "$(git -C "${PROJECT_ROOT}" describe --always --dirty --tags 2>/dev/null || true)"
        printf 'Last commit: %s\n' "$(git -C "${PROJECT_ROOT}" log -1 --format='%cI %h %s' 2>/dev/null || true)"
    else
        printf 'no\n'
    fi
    printf 'PHP: %s\n' "$(php -v 2>/dev/null | head -n 1 || printf 'not available')"
    printf 'Tar: %s\n' "$(tar --version 2>/dev/null | head -n 1 || printf 'version unavailable')"
} > "${CONTEXT_DIR}/git_head.txt"

if git -C "${PROJECT_ROOT}" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    git -C "${PROJECT_ROOT}" status --short --branch > "${CONTEXT_DIR}/git_status.txt"
    git -C "${PROJECT_ROOT}" diff --stat > "${CONTEXT_DIR}/git_diff_stat.txt"
    git -C "${PROJECT_ROOT}" diff --name-status > "${CONTEXT_DIR}/git_diff_names.txt"
    git -C "${PROJECT_ROOT}" diff --cached --stat > "${CONTEXT_DIR}/git_staged_diff_stat.txt"
    git -C "${PROJECT_ROOT}" ls-files > "${CONTEXT_DIR}/git_tracked_files.txt"
else
    printf 'Not a Git working tree.\n' > "${CONTEXT_DIR}/git_status.txt"
    : > "${CONTEXT_DIR}/git_diff_stat.txt"
    : > "${CONTEXT_DIR}/git_diff_names.txt"
    : > "${CONTEXT_DIR}/git_staged_diff_stat.txt"
    : > "${CONTEXT_DIR}/git_tracked_files.txt"
fi

{
    printf 'Current migration files: '
    find "${PROJECT_ROOT}/database/migrations" -maxdepth 1 -type f -name '*.sql' 2>/dev/null | wc -l | tr -d ' '
    printf '\nLatest migration: '
    find "${PROJECT_ROOT}/database/migrations" -maxdepth 1 -type f -name '*.sql' -print 2>/dev/null \
        | LC_ALL=C sort \
        | tail -n 1 \
        | sed "s#${PROJECT_ROOT}/##"
    printf '\nPHP files under app/: '
    find "${PROJECT_ROOT}/app" -type f -name '*.php' 2>/dev/null | wc -l | tr -d ' '
    printf '\nPHP test files: '
    find "${PROJECT_ROOT}/scripts/test" -type f -name '*.php' 2>/dev/null | wc -l | tr -d ' '
    printf '\nDocumentation files: '
    find "${PROJECT_ROOT}/docs" -type f 2>/dev/null | wc -l | tr -d ' '
    printf '\n'
} > "${CONTEXT_DIR}/project_counts.txt"

# The archive starts at "." rather than "*", so maintained dotfiles such as
# .gitignore and configuration examples are included.
tar \
    --exclude-vcs \
    --exclude='./bundle' \
    --exclude='./artsfolio-video-factory' \
    --exclude='./storage' \
    --exclude='./vendor' \
    --exclude='./node_modules' \
    --exclude='./.patch_backups' \
    --exclude='./.update-backups' \
    --exclude='./.idea' \
    --exclude='./.vscode' \
    --exclude='./.fleet' \
    --exclude='./coverage' \
    --exclude='./.coverage' \
    --exclude='./build' \
    --exclude='./dist' \
    --exclude='./tmp' \
    --exclude='./temp' \
    --exclude='./cache' \
    --exclude='./logs' \
    --exclude='./log' \
    --exclude='./sessions' \
    --exclude='./._*' \
    --exclude='*/._*' \
    --exclude='./.DS_Store' \
    --exclude='*/.DS_Store' \
    --exclude='./.env' \
    --exclude='./.env.local' \
    --exclude='./.env.production' \
    --exclude='./.env.prod' \
    --exclude='./.env.development' \
    --exclude='./.env.test' \
    --exclude='*/.env' \
    --exclude='*/.env.local' \
    --exclude='*/.env.production' \
    --exclude='*/.env.prod' \
    --exclude='*/.env.development' \
    --exclude='*/.env.test' \
    --exclude='./.application_secrets' \
    --exclude='*/.application_secrets' \
    --exclude='*.pem' \
    --exclude='*.key' \
    --exclude='*.p12' \
    --exclude='*.pfx' \
    --exclude='*.crt' \
    --exclude='*.cer' \
    --exclude='*.log' \
    --exclude='*.bak' \
    --exclude='*.backup' \
    --exclude='*.orig' \
    --exclude='*.rej' \
    --exclude='*~' \
    --exclude='*.swp' \
    --exclude='*.swo' \
    --exclude='*.tar' \
    --exclude='*.tar.gz' \
    --exclude='*.tgz' \
    --exclude='*.zip' \
    --exclude='*.7z' \
    --exclude='*.gz' \
    --exclude='*.sqlite' \
    --exclude='*.sqlite3' \
    --exclude='*.db' \
    -czf "${OUTPUT_FILE}" \
    -C "${PROJECT_ROOT}" \
    .

[[ -s "${OUTPUT_FILE}" ]] || fail "Bundle was not created or is empty: ${OUTPUT_FILE}"

# Create a portable SHA-256 sidecar only when explicitly requested.
if [[ "${CREATE_CHECKSUM}" == true ]]; then
    if command -v shasum >/dev/null 2>&1; then
        (
            cd "${BUNDLE_DIR}"
            shasum -a 256 "$(basename "${OUTPUT_FILE}")" > "$(basename "${CHECKSUM_FILE}")"
        )
    elif command -v sha256sum >/dev/null 2>&1; then
        (
            cd "${BUNDLE_DIR}"
            sha256sum "$(basename "${OUTPUT_FILE}")" > "$(basename "${CHECKSUM_FILE}")"
        )
    else
        printf '[WARN] Neither shasum nor sha256sum is installed; checksum omitted.\n' >&2
    fi
fi

# Validate that important source files are present and sensitive/runtime paths
# are absent before declaring success.
archive_listing="$(mktemp)"
trap 'rm -f -- "${archive_listing}"; cleanup' EXIT INT TERM
tar -tzf "${OUTPUT_FILE}" > "${archive_listing}"

grep -qE '^\./PROJECT_STATE\.md$' "${archive_listing}" \
    || fail "Bundle validation failed: PROJECT_STATE.md is missing"
grep -qE '^\./composer\.json$' "${archive_listing}" \
    || fail "Bundle validation failed: composer.json is missing"
grep -qE '^\./app/' "${archive_listing}" \
    || fail "Bundle validation failed: app/ is missing"
grep -qE '^\./database/migrations/' "${archive_listing}" \
    || fail "Bundle validation failed: migrations are missing"
grep -qE '^\./scripts/test/' "${archive_listing}" \
    || fail "Bundle validation failed: tests are missing"
grep -qE '^\./docs/' "${archive_listing}" \
    || fail "Bundle validation failed: documentation is missing"
grep -qE '^\./\.artsfolio_bundle_context_[^/]+/README\.txt$' "${archive_listing}" \
    || fail "Bundle validation failed: generated context manifest is missing"

if grep -qE '(^|/)(storage|vendor|node_modules|bundle|\.git)(/|$)' "${archive_listing}"; then
    fail "Bundle validation failed: an excluded runtime/dependency directory was included"
fi

if grep -qE '(^|/)(\.env|\.env\.(local|production|prod|development|test)|\.application_secrets)$|\.(pem|key|p12|pfx)$' "${archive_listing}"; then
    fail "Bundle validation failed: a sensitive file pattern was included"
fi

file_count="$(wc -l < "${archive_listing}" | tr -d ' ')"
bundle_size="$(du -h "${OUTPUT_FILE}" | awk '{print $1}')"

printf '[PASS] ArtsFolio AI context bundle created.\n'
printf '[INFO] File: [%s]\n' "${OUTPUT_FILE}"
printf '[INFO] Size: %s\n' "${bundle_size}"
printf '[INFO] Archived entries: %s\n' "${file_count}"
if [[ "${CREATE_CHECKSUM}" == true && -f "${CHECKSUM_FILE}" ]]; then
    printf '[INFO] Checksum: [%s]\n' "${CHECKSUM_FILE}"
fi

# End of file.
