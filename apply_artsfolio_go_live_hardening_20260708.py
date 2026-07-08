#!/usr/bin/python3
"""
Apply ArtsFolio go-live hardening repairs.

This script is intentionally idempotent and production-oriented. Run it from the
ArtsFolio project root on the development workstation, then run the verification
commands printed at the end before deployment.
"""
from __future__ import annotations

import os
import shutil
import sys
from datetime import datetime
from pathlib import Path

PROJECT_MARKERS = ["app/Http/Routes/platform.php", "scripts/test/preflight.sh", "PROJECT_STATE.md"]
STAMP = datetime.now().strftime("%Y%m%d%H%M%S")


def fail(message: str) -> None:
    print(f"[FAIL] {message}", file=sys.stderr)
    sys.exit(1)


def info(message: str) -> None:
    print(f"[PASS] {message}")


def write_status(message: str) -> None:
    print(f"[WRITE] {message}")


def project_root() -> Path:
    root = Path.cwd().resolve()
    for marker in PROJECT_MARKERS:
        if not (root / marker).exists():
            fail(f"Run this script from the ArtsFolio project root; missing {marker}")
    return root


def backup_file(root: Path, backup_root: Path, relative: str) -> None:
    source = root / relative
    if source.exists():
        target = backup_root / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(source, target)


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def write_text_if_changed(path: Path, content: str) -> bool:
    current = path.read_text(encoding="utf-8") if path.exists() else None
    if current == content:
        return False
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")
    return True


def ensure_eof_comment(content: str, comment: str = "// End of file.") -> str:
    stripped = content.rstrip()
    if stripped.endswith(comment):
        return stripped + "\n"
    return stripped + "\n\n" + comment + "\n"


def ensure_md_eof_comment(content: str) -> str:
    stripped = content.rstrip()
    for marker in ("<!-- End of file. -->", "# End of file."):
        if stripped.endswith(marker):
            return stripped + "\n"
    return stripped + "\n\n<!-- End of file. -->\n"


def fix_platform_job_route(root: Path, backup_root: Path) -> None:
    relative = "app/Http/Routes/platform.php"
    path = root / relative
    backup_file(root, backup_root, relative)
    source = read_text(path)
    old = "$router->get('/platform/admin/jobs/{id}', fn (Request $request, string $id): Response => (new PlatformAdminJobsController(new RequirePlatformRole(new MembershipRepository($pdo)), new JobAdminRepository($pdo), new JobAdminService($pdo, new JobAttemptRepository($pdo)), new CsrfTokenService(), new AuditLogRepository($pdo), new JobAttemptRepository($pdo)))->show($request, $currentUser, (int) $id));"
    new = "$router->get('/platform/admin/jobs/{id}', fn (Request $request, array $params): Response => (new PlatformAdminJobsController(new RequirePlatformRole(new MembershipRepository($pdo)), new JobAdminRepository($pdo), new JobAdminService($pdo, new JobAttemptRepository($pdo)), new CsrfTokenService(), new AuditLogRepository($pdo), new JobAttemptRepository($pdo)))->show($request, $currentUser, (int) ($params['id'] ?? 0)));"
    if old in source:
        source = source.replace(old, new, 1)
    elif new not in source:
        fail("Could not locate the platform job detail route to repair.")
    source = ensure_eof_comment(source)
    if write_text_if_changed(path, source):
        write_status(relative)
    else:
        info(f"No change needed: {relative}")


def fix_billing_scheduler_unit(root: Path, backup_root: Path) -> None:
    relative = "scripts/systemd/artsfolio-billing-scheduler.service"
    path = root / relative
    backup_file(root, backup_root, relative)
    source = read_text(path)
    if "Environment=ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env" not in source:
        marker = "WorkingDirectory=/var/www/artsfolio\n"
        if marker not in source:
            fail("Could not locate WorkingDirectory in billing scheduler unit.")
        source = source.replace(marker, marker + "Environment=ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env\n", 1)
    source = ensure_eof_comment(source, "# End of file.")
    if write_text_if_changed(path, source):
        write_status(relative)
    else:
        info(f"No change needed: {relative}")


def add_job_route_static_test(root: Path, backup_root: Path) -> None:
    relative = "scripts/test/platform_job_detail_route_params_static.php"
    path = root / relative
    if path.exists():
        backup_file(root, backup_root, relative)
    content = r"""<?php

declare(strict_types=1);

/**
 * Regression check for parameterized platform job detail routing.
 *
 * App\\Http\\Router dispatches route parameters as a single associative array.
 * The /platform/admin/jobs/{id} route must therefore accept array $params and
 * read $params['id']; accepting string $id causes a runtime TypeError.
 */

$root = dirname(__DIR__, 2);
$routeFile = $root . '/app/Http/Routes/platform.php';
$source = file_get_contents($routeFile);

if ($source === false) {
    fwrite(STDERR, "Could not read platform route file.\n");
    exit(1);
}

$required = [
    "\$router->get('/platform/admin/jobs/{id}', fn (Request \$request, array \$params): Response",
    "(\$params['id'] ?? 0)",
];

foreach ($required as $needle) {
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, "Missing platform job detail route marker: {$needle}\n");
        exit(1);
    }
}

$forbidden = "\$router->get('/platform/admin/jobs/{id}', fn (Request \$request, string \$id): Response";
if (str_contains($source, $forbidden)) {
    fwrite(STDERR, "Platform job detail route still accepts string id instead of array params.\n");
    exit(1);
}

echo "Platform job detail route parameter static check passed.\n";

// End of file.
"""
    if write_text_if_changed(path, content):
        write_status(relative)
    else:
        info(f"No change needed: {relative}")


def patch_preflight(root: Path, backup_root: Path) -> None:
    relative = "scripts/test/preflight.sh"
    path = root / relative
    backup_file(root, backup_root, relative)
    source = read_text(path)
    source = source.replace('find app public scripts -name "*.php" -print0 | sort -z', 'find app public scripts -name "*.php" ! -name "._*" -print0 | sort -z')
    source = source.replace('find scripts -name "*.sh" -print0 | sort -z', 'find scripts -name "*.sh" ! -name "._*" -print0 | sort -z')
    marker = "run_php scripts/test/platform_operations_run_detail_static.php\n"
    addition = "run_php scripts/test/platform_job_detail_route_params_static.php\n"
    if addition not in source:
        if marker not in source:
            fail("Could not locate platform operations static test marker in preflight.")
        source = source.replace(marker, marker + "\n" + addition, 1)
    source = ensure_eof_comment(source, "# End of file.")
    if write_text_if_changed(path, source):
        write_status(relative)
    else:
        info(f"No change needed: {relative}")


def append_section(path: Path, title: str, body: str) -> bool:
    source = read_text(path) if path.exists() else ""
    if title in source:
        return False
    source = source.rstrip()
    for marker in ("<!-- End of file. -->", "# End of file."):
        if source.endswith(marker):
            source = source[: -len(marker)].rstrip()
    source = source + "\n\n" + title + "\n\n" + body.strip() + "\n\n<!-- End of file. -->\n"
    return write_text_if_changed(path, source)


def update_docs(root: Path, backup_root: Path) -> None:
    docs = {
        "docs/admin/production-preflight.md": (
            "## Go-live gate",
            """
Before public signup, live checkout, or paid subscriptions are enabled, run the production preflight from the production tree and confirm every route below loads without a 500 response.

```bash
cd /var/www/artsfolio

set -a
. /etc/artsfolio/artsfolio.env
set +a

php scripts/database/check_migration_integrity.php
php scripts/database/check_schema_health.php
bash scripts/test/preflight.sh
ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env ./scripts/deploy/healthcheck.sh
ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env php scripts/ops/monitor_artsfolio.php
```

Browser smoke routes:

```text
https://artsfol.io/
https://artsfol.io/login
https://artsfol.io/platform/admin
https://artsfol.io/platform/admin/operations
https://artsfol.io/platform/admin/jobs
https://bxiie.com/
https://bxiie.com/login
https://bxiie.com/admin
https://bxiie.com/admin/artworks
https://bxiie.com/admin/settings
https://bxiie.com/cart
```

The platform job detail route is covered by `scripts/test/platform_job_detail_route_params_static.php` because the router passes parameterized route values as an associative array. Do not change `/platform/admin/jobs/{id}` back to a scalar route argument.
""",
        ),
        "docs/admin/commerce.md": (
            "## Live commerce launch checklist",
            """
Run a full Stripe test-mode sale before enabling live commerce for any artist.

1. Confirm Platform Admin has the Stripe secret key and webhook secret configured.
2. Confirm Stripe Connect is enabled on the platform Stripe account.
3. Confirm the artist completed Connect Stripe onboarding from Settings in the sidebar.
4. Confirm checkout is blocked while the connected account is not ready.
5. Create a low-price test artwork or variant and publish it.
6. Add the artwork to cart and complete Stripe Checkout in test mode.
7. Confirm the order is marked paid in ArtsFolio and in Stripe.
8. Confirm the payment intent uses the artist connected account as the destination.
9. Confirm inventory and order workflow statuses changed as expected.
10. Confirm buyer and artist emails are queued or sent.
11. Issue a partial refund and compare ArtsFolio against Stripe.
12. Issue a full refund on a separate order and compare ArtsFolio against Stripe.

No further refund should be attempted after a Stripe error until the local order row and the Stripe dashboard are compared.
""",
        ),
        "docs/dev/stripe-connect.md": (
            "## Verification commands",
            """
Use these commands before merging or deploying commerce changes:

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio

composer dump-autoload
php scripts/test/platform_job_detail_route_params_static.php
php scripts/test/sales_refund_csrf_method_static.php
php scripts/test/phase8_routing_static.php
bash scripts/test/preflight.sh
```

Use production-safe checks after deployment:

```bash
cd /var/www/artsfolio

set -a
. /etc/artsfolio/artsfolio.env
set +a

php scripts/database/check_migration_integrity.php
php scripts/database/check_schema_health.php
ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env ./scripts/deploy/healthcheck.sh
```
""",
        ),
        "docs/user/billing-and-payouts.md": (
            "## How you get paid for artwork sales",
            """
Click **Settings** in the sidebar and find **How you get paid**. Click **Connect Stripe** and complete Stripe-hosted onboarding. Stripe collects your banking, identity, tax, and business details directly.

After Stripe sends you back to ArtsFolio, return to **Settings** and confirm your payout status says your account is connected and ready for checkout. Buyers cannot complete online checkout until Stripe confirms that your account can accept charges and receive payouts.

When an artwork sells, Stripe processes the buyer payment, ArtsFolio applies the platform commission and card-processing costs, and the artist proceeds are routed through your connected Stripe account. Stripe controls the exact payout timing shown in your Stripe dashboard.
""",
        ),
        "docs/user/tenant-admin-help.md": (
            "## Launch readiness checklist",
            """
Before sharing your site widely, confirm these pieces are ready:

1. Your logo and site images display without stretching.
2. Your About and Contact pages are filled in.
3. Your public artworks are published and grouped into the right sections.
4. Your sale-ready artworks have prices, inventory, shipping, and Stripe payout setup complete.
5. You have sent yourself a test contact message and joined your own email list.
6. Your custom domain opens over HTTPS if you are using one.
""",
        ),
    }

    for relative, (title, body) in docs.items():
        backup_file(root, backup_root, relative)
        path = root / relative
        if append_section(path, title, body):
            write_status(relative)
        else:
            info(f"No change needed: {relative}")


def update_project_state(root: Path, backup_root: Path) -> None:
    relative = "PROJECT_STATE.md"
    path = root / relative
    backup_file(root, backup_root, relative)
    source = read_text(path)
    heading = "## 2026-07-08 Go-live hardening pass"
    if heading in source:
        info(f"No change needed: {relative}")
        return
    section = f"""{heading}
- Repaired `/platform/admin/jobs/{{id}}` so it accepts the router parameter array and reads `$params['id']`, avoiding a runtime TypeError on platform job detail pages.
- Added `scripts/test/platform_job_detail_route_params_static.php` and included it in `scripts/test/preflight.sh`.
- Updated preflight syntax scans to ignore macOS AppleDouble `._*` files so Finder metadata does not become part of application validation.
- Added `ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env` to `scripts/systemd/artsfolio-billing-scheduler.service` so billing scheduler runs with the same production environment convention as the other services.
- Added go-live, commerce, Stripe Connect, billing/payout, and artist launch readiness documentation.
- Moved deploy debris such as AppleDouble files, `.patch-backups`, root `.docx` exports, and known `.bak` controller copies into this run's `.update-backups` folder instead of deleting them outright.

"""
    source = section + source.lstrip()
    if write_text_if_changed(path, source):
        write_status(relative)


def clean_deploy_debris(root: Path, backup_root: Path) -> None:
    debris_root = backup_root / "deploy-debris"
    moved = 0

    patterns = ["._*", ".patch-backups", ":1", "ArtsFolio_Tenant_Admin_Training_Video_Scripts_20260708.docx"]
    explicit = [
        "app/Http/Controllers/Tenant/Admin/ContactMessagesController.php.bak-contact-admin-v1",
        "app/Http/Controllers/Tenant/Admin/ArtworkPlacementController.php.bak-artwork-placement-20260610125513",
    ]

    candidates: list[Path] = []
    for pattern in patterns:
        candidates.extend(root.rglob(pattern) if pattern == "._*" else root.glob(pattern))
    candidates.extend(root / item for item in explicit)

    for source in sorted(set(candidates)):
        if not source.exists():
            continue
        try:
            source.relative_to(backup_root)
            continue
        except ValueError:
            pass
        relative = source.relative_to(root)
        target = debris_root / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        if target.exists():
            if target.is_dir():
                shutil.rmtree(target)
            else:
                target.unlink()
        shutil.move(str(source), str(target))
        moved += 1

    if moved:
        write_status(f"moved {moved} deploy-debris item(s) to {debris_root.relative_to(root)}")
    else:
        info("No deploy debris found to move")


def main() -> None:
    root = project_root()
    backup_root = root / ".update-backups" / f"go-live-hardening-{STAMP}"
    backup_root.mkdir(parents=True, exist_ok=True)
    info(f"Backup directory: {backup_root}")

    fix_platform_job_route(root, backup_root)
    fix_billing_scheduler_unit(root, backup_root)
    add_job_route_static_test(root, backup_root)
    patch_preflight(root, backup_root)
    update_docs(root, backup_root)
    update_project_state(root, backup_root)
    clean_deploy_debris(root, backup_root)

    print("[NEXT] Run:")
    print("  composer dump-autoload")
    print("  php -l app/Http/Routes/platform.php")
    print("  php -l scripts/test/platform_job_detail_route_params_static.php")
    print("  php scripts/test/platform_job_detail_route_params_static.php")
    print("  php scripts/test/phase8_routing_static.php")
    print("  bash scripts/test/preflight.sh")
    print("  git status --short")


if __name__ == "__main__":
    main()

# End of file.
