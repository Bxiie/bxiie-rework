#!/usr/bin/python3
"""
Apply the ArtsFolio email outbox UTC scheduling fix.

This patch makes email_outbox queueing, claiming, and lifecycle scheduling use
UTC consistently so the operations monitor and email worker agree on whether a
queued email is actually ready to send.
"""

from __future__ import annotations

import datetime as _dt
import shutil
import sys
from pathlib import Path

ROOT = Path.cwd()
STAMP = _dt.datetime.now().strftime('%Y%m%d%H%M%S')
BACKUP = ROOT / '.update-backups' / f'email-utc-outbox-{STAMP}'

FILES = [
    'app/Platform/Email/EmailOutboxRepository.php',
    'app/Platform/Signup/TenantSignupService.php',
    'scripts/email/queue_lifecycle_emails.php',
    'scripts/email/reconcile_tenant_lifecycle_emails.php',
    'scripts/test/preflight.sh',
    'PROJECT_STATE.md',
]


def fail(message: str) -> None:
    print(f'[FAIL] {message}', file=sys.stderr)
    sys.exit(1)


def read(rel: str) -> str:
    path = ROOT / rel
    if not path.exists():
        fail(f'Missing required file: {rel}')
    return path.read_text()


def write(rel: str, text: str) -> None:
    path = ROOT / rel
    if path.exists() and not (BACKUP / rel).exists():
        (BACKUP / rel).parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(path, BACKUP / rel)
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(text)
    print(f'[PASS] Updated {rel}')


def replace_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count != 1:
        fail(f'{label}: expected exactly one match, found {count}')
    return text.replace(old, new, 1)


def ensure_backup_root() -> None:
    BACKUP.mkdir(parents=True, exist_ok=True)
    print(f'[PASS] Backup directory: {BACKUP}')


def patch_email_outbox_repository() -> None:
    rel = 'app/Platform/Email/EmailOutboxRepository.php'
    text = read(rel)

    text = text.replace('DATE_ADD(CURRENT_TIMESTAMP, INTERVAL :available_after SECOND)', 'DATE_ADD(UTC_TIMESTAMP(), INTERVAL :available_after SECOND)')
    text = text.replace('available_at <= CURRENT_TIMESTAMP', 'available_at <= UTC_TIMESTAMP()')
    text = text.replace('updated_at = CURRENT_TIMESTAMP', 'updated_at = UTC_TIMESTAMP()')
    text = text.replace('sent_at = CURRENT_TIMESTAMP', 'sent_at = UTC_TIMESTAMP()')
    text = text.replace('failed_at = CURRENT_TIMESTAMP', 'failed_at = UTC_TIMESTAMP()')

    old = '''    /**
     * Ensures every queued email carries ArtsFolio identity, including older
     * hard-coded invite and notification senders that do not use template files.
     */
    /**
     * Produces plain-text and branded HTML bodies for outbox rows.
     *
     * The SMTP worker sends multipart/alternative when body_html is populated.
     */
    private function brandBodies(string $subject, string $bodyText): array
    {
        return BrandedEmail::render($subject, $bodyText);
    }
'''
    new = '''    /**
     * Produces plain-text and branded HTML bodies for outbox rows.
     *
     * The SMTP worker sends multipart/alternative when body_html is populated.
     * Existing explicit HTML bodies are accepted for signature compatibility,
     * but the central ArtsFolio shell remains the canonical branding source.
     */
    private function brandBodies(string $subject, string $bodyText, ?string $bodyHtml = null): array
    {
        return BrandedEmail::render($subject, $bodyText);
    }
'''
    text = replace_once(text, old, new, rel + ' brandBodies')

    required = [
        'DATE_ADD(UTC_TIMESTAMP(), INTERVAL :available_after SECOND)',
        'available_at <= UTC_TIMESTAMP()',
        'sent_at = UTC_TIMESTAMP()',
        'failed_at = UTC_TIMESTAMP()',
        'private function brandBodies(string $subject, string $bodyText, ?string $bodyHtml = null): array',
    ]
    for needle in required:
        if needle not in text:
            fail(f'{rel}: missing required patch marker: {needle}')

    write(rel, text)


def patch_tenant_signup_service() -> None:
    rel = 'app/Platform/Signup/TenantSignupService.php'
    text = read(rel)
    text = replace_once(
        text,
        "'available_at' => date('Y-m-d H:i:s', time() + $delaySeconds),",
        "'available_at' => gmdate('Y-m-d H:i:s', time() + $delaySeconds),",
        rel + ' lifecycle available_at',
    )
    text = replace_once(
        text,
        "        return date('Y-m-d H:i:s');",
        "        return gmdate('Y-m-d H:i:s');",
        rel + ' now utc',
    )
    write(rel, text)


def patch_lifecycle_scripts() -> None:
    rel = 'scripts/email/queue_lifecycle_emails.php'
    text = read(rel)
    text = replace_once(
        text,
        "$availableAt = date('Y-m-d H:i:s', time() + (int) $message['delay_seconds']);",
        "$availableAt = gmdate(\'Y-m-d H:i:s\', time() + (int) $message[\'delay_seconds\']);",
        rel + ' availableAt utc',
    )
    text = text.replace('CURRENT_TIMESTAMP,', 'UTC_TIMESTAMP(),')
    write(rel, text)

    rel = 'scripts/email/reconcile_tenant_lifecycle_emails.php'
    text = read(rel)
    text = text.replace('CURRENT_TIMESTAMP,', 'UTC_TIMESTAMP(),')
    write(rel, text)


def create_static_test() -> None:
    rel = 'scripts/test/email_outbox_utc_static.php'
    text = '''<?php

declare(strict_types=1);

/**
 * Static regression checks for email outbox UTC scheduling.
 *
 * The operations monitor evaluates email_outbox.available_at against
 * UTC_TIMESTAMP(). These checks keep queue writers and worker claim logic on
 * the same clock so queued lifecycle emails do not appear falsely stale before
 * the worker considers them ready.
 */

$root = dirname(__DIR__, 2);
$checks = [
    'app/Platform/Email/EmailOutboxRepository.php' => [
        'DATE_ADD(UTC_TIMESTAMP(), INTERVAL :available_after SECOND)',
        'available_at <= UTC_TIMESTAMP()',
        'sent_at = UTC_TIMESTAMP()',
        'failed_at = UTC_TIMESTAMP()',
    ],
    'app/Platform/Signup/TenantSignupService.php' => [
        <<<'NEEDLE'
'available_at' => gmdate('Y-m-d H:i:s', time() + $delaySeconds),
NEEDLE,
        <<<'NEEDLE'
return gmdate('Y-m-d H:i:s');
NEEDLE,
    ],
    'scripts/email/queue_lifecycle_emails.php' => [
        <<<'NEEDLE'
$availableAt = gmdate('Y-m-d H:i:s', time() + (int) $message['delay_seconds']);
NEEDLE,
        'UTC_TIMESTAMP(),',
    ],
    'scripts/email/reconcile_tenant_lifecycle_emails.php' => [
        'UTC_TIMESTAMP(),',
    ],
];

$problems = [];

foreach ($checks as $relativePath => $needles) {
    $path = $root . '/' . $relativePath;
    if (!is_file($path)) {
        $problems[] = "Missing file: {$relativePath}";
        continue;
    }

    $source = (string) file_get_contents($path);
    foreach ($needles as $needle) {
        if (!str_contains($source, $needle)) {
            $problems[] = "Missing {$needle} in {$relativePath}";
        }
    }
}

if ($problems !== []) {
    fwrite(STDERR, "Email outbox UTC static check failed:\n");
    foreach ($problems as $problem) {
        fwrite(STDERR, " - {$problem}\n");
    }
    exit(1);
}

echo "Email outbox UTC static checks passed.\n";

// End of file.
'''
    write(rel, text)


def patch_preflight() -> None:
    rel = 'scripts/test/preflight.sh'
    text = read(rel)
    line = 'run_php scripts/test/email_outbox_utc_static.php'
    if line in text:
        print(f'[PASS] {rel} already runs email_outbox_utc_static.php')
        return
    marker = 'php scripts/test/email_outbox_grid_containment_static.php\n'
    if marker in text:
        text = text.replace(marker, marker + '\n' + line + '\n', 1)
    else:
        text = text.replace("printf '[PASS] Preflight completed successfully.\\n'\n", line + "\n\nprintf '[PASS] Preflight completed successfully.\\n'\n", 1)
    write(rel, text)


def patch_docs() -> None:
    dev_rel = 'docs/dev/email-worker.md'
    dev_text = read(dev_rel) if (ROOT / dev_rel).exists() else '# Email worker\n\n'
    note = '''

## UTC scheduling contract

`email_outbox.available_at`, `sent_at`, `failed_at`, and `updated_at` are evaluated as UTC timestamps. Queue writers must use `gmdate('Y-m-d H:i:s', ...)` or database `UTC_TIMESTAMP()` instead of local-time `date()` or `CURRENT_TIMESTAMP`. The operations monitor and the email worker both compare ready rows against `UTC_TIMESTAMP()` so a queued email cannot be reported as stale before the worker considers it claimable.

# End of file.
'''
    if '## UTC scheduling contract' not in dev_text:
        dev_text = dev_text.replace('\n# End of file.\n', '')
        dev_text = dev_text.rstrip() + note
        write(dev_rel, dev_text)
    else:
        print(f'[PASS] {dev_rel} already documents UTC scheduling')

    admin_rel = 'docs/admin/email-outbox.md'
    admin_text = read(admin_rel) if (ROOT / admin_rel).exists() else '# Email Outbox\n\n'
    note = '''

## Queued-but-not-sent timestamps

Outbox timestamps are stored and monitored in UTC. If Platform Admin → Email Outbox shows queued rows and the monitor reports `queue.email.oldest_ready_age_minutes`, first check that `artsfolio-email-worker@1.service` and `artsfolio-email-worker@2.service` are active. Then run `ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env php scripts/workers/email_run_once.php` from `/var/www/artsfolio` to force a single claim/send cycle and print the transport result.

# End of file.
'''
    if '## Queued-but-not-sent timestamps' not in admin_text:
        admin_text = admin_text.replace('\n# End of file.\n', '')
        admin_text = admin_text.rstrip() + note
        write(admin_rel, admin_text)
    else:
        print(f'[PASS] {admin_rel} already documents queued timestamp triage')


def patch_project_state() -> None:
    rel = 'PROJECT_STATE.md'
    text = read(rel)
    entry = '''

## Email outbox UTC scheduling
- Email outbox readiness is now evaluated on a single UTC clock: queue writers use `gmdate()` or `UTC_TIMESTAMP()`, and `EmailOutboxRepository::claimNext()` compares `available_at` to `UTC_TIMESTAMP()`.
- This prevents `queue.email.oldest_ready_age_minutes` from going critical because the operations monitor sees rows as UTC-ready before the email worker, using local `CURRENT_TIMESTAMP`, would claim them.
- Email worker units remain `artsfolio-email-worker@1.service` and `artsfolio-email-worker@2.service`; manual one-shot verification is `ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env php scripts/workers/email_run_once.php` from `/var/www/artsfolio`.
'''
    if '## Email outbox UTC scheduling' not in text:
        text = text.rstrip() + entry + '\n'
        write(rel, text)
    else:
        print(f'[PASS] {rel} already has Email outbox UTC scheduling section')


def run_validations() -> None:
    targets = [
        'app/Platform/Email/EmailOutboxRepository.php',
        'app/Platform/Signup/TenantSignupService.php',
        'scripts/email/queue_lifecycle_emails.php',
        'scripts/email/reconcile_tenant_lifecycle_emails.php',
        'scripts/test/email_outbox_utc_static.php',
    ]
    for rel in targets:
        path = ROOT / rel
        if not path.exists():
            fail(f'Validation target missing: {rel}')

    print('[PASS] Patch files are present. Run php -l and preflight commands below before deploy.')


def main() -> None:
    required_dirs = ['app', 'scripts', 'database']
    for directory in required_dirs:
        if not (ROOT / directory).is_dir():
            fail(f'Run from the ArtsFolio project root; missing ./{directory}')

    ensure_backup_root()
    patch_email_outbox_repository()
    patch_tenant_signup_service()
    patch_lifecycle_scripts()
    create_static_test()
    patch_preflight()
    patch_docs()
    patch_project_state()
    run_validations()
    print('[PASS] Email UTC outbox patch applied.')
    print(f'[PASS] Backups saved under {BACKUP}')


if __name__ == '__main__':
    main()

# End of file.
