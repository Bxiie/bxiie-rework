<?php

declare(strict_types=1);

namespace App\Platform\Operations;

/** Starts a narrowly allow-listed privileged operations task through sudo. */
final class OperationsTaskLauncher
{
    private const HELPER = '/usr/local/sbin/artsfolio-admin-task';

    /** @return array{ok: bool, output: string, exit_code: int} */
    public function start(string $task): array
    {
        if (!in_array($task, ['monitor', 'backup', 'integrity-check', 'restore-test'], true)) {
            return ['ok' => false, 'output' => 'Unsupported operations task.', 'exit_code' => 64];
        }

        $process = proc_open(
            ['/usr/bin/sudo', '-n', self::HELPER, $task],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            null,
            ['bypass_shell' => true],
        );

        if (!is_resource($process)) {
            return ['ok' => false, 'output' => 'Unable to start the privileged operations helper.', 'exit_code' => 70];
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'ok' => $exitCode === 0,
            'output' => trim((string) $stdout . ($stderr !== '' ? "\n" . $stderr : '')),
            'exit_code' => $exitCode,
        ];
    }
}

// End of file.
