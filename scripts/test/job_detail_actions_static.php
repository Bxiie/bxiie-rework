<?php

declare(strict_types=1);

// Regression coverage: the platform job detail page (/platform/admin/jobs/{id})
// previously rendered no Requeue/Cancel actions at all -- JobsController::show()
// built a read-only <dl>/<pre> body with no forms, while index() (the list view)
// had them. An operator following a job link from the list had no way to act
// on it without navigating back. This asserts show() renders both actions and
// that they post to the same /platform/admin/jobs/action endpoint index() uses.

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/app/Http/Controllers/Platform/Admin/JobsController.php') ?: '';
$failures = [];

if (!preg_match('/function show\(.*?\n    \}\n/s', $controller, $showMatch)) {
    $failures[] = 'Could not isolate JobsController::show().';
}
$show = $showMatch[1] ?? ($showMatch[0] ?? '');

foreach ([
    'action="/platform/admin/jobs/action"',
    'name="job_admin_action" value="requeue"',
    'name="job_admin_action" value="cancel"',
    'name="job_id" value="{$jobId}"',
] as $needle) {
    if (!str_contains($show, $needle)) {
        $failures[] = "JobsController::show() missing {$needle}";
    }
}

// The requeue/cancel action handler must accept a return_to back to the
// detail page rather than always bouncing to the list, or clicking Requeue
// from a job's own detail page would silently drop the operator onto the list.
if (!str_contains($controller, "private function returnTo(string \$fallback): string")) {
    $failures[] = 'JobsController is missing a returnTo() helper for post-action redirects.';
}
if (!str_contains($controller, "Location' => \$this->returnTo('/platform/admin/jobs')")) {
    $failures[] = 'JobsController::action() does not redirect via returnTo().';
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Job detail action buttons static checks passed.\n";

// End of file.
