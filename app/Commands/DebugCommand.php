<?php

declare(strict_types=1);

namespace App\Commands;

use App\Concerns\HasBrandedOutput;
use App\Services\Debug\DebugLogService;
use LaravelZero\Framework\Commands\Command;

final class DebugCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'debug
                          {action? : Action to perform (status, enable, disable, logs, clear)}
                          {--lines=50 : Number of log lines to show}
                          {--level= : Filter logs by level (error, warning, info, debug)}
                          {--session : Show only current session logs}';

    protected $description = 'Debug tools and log viewer for tuti-cli';

    public function handle(DebugLogService $debugService): int
    {
        $action = $this->argument('action') ?? 'status';

        return match ($action) {
            'status' => $this->showStatus($debugService),
            'enable' => $this->enableDebug($debugService),
            'disable' => $this->disableDebug($debugService),
            'logs' => $this->showLogs($debugService),
            'clear' => $this->clearLogs($debugService),
            'errors' => $this->showErrors($debugService),
            default => $this->showHelp(),
        };
    }

    private function showStatus(DebugLogService $debugService): int
    {
        $this->brandedHeader('Debug Status');

        $isEnabled = $debugService->isEnabled();
        $logPath = $debugService->getGlobalLogPath();
        $logFile = $logPath . '/tuti.log';
        $logSize = file_exists($logFile) ? $this->formatBytes(filesize($logFile)) : '0 B';
        $sessionLogCount = count($debugService->getSessionLogs());
        $errorCount = count($debugService->getErrors());

        $this->box('Debug Configuration', [
            'Debug Mode' => $isEnabled ? '✅ Enabled' : '❌ Disabled',
            'Log Directory' => $logPath,
            'Log File Size' => $logSize,
            'Session Logs' => (string) $sessionLogCount,
            'Errors in Session' => $errorCount > 0 ? "⚠️ {$errorCount}" : '✅ 0',
        ], 70, true);

        if ($errorCount > 0) {
            $this->newLine();
            $this->warning('There are errors in the current session. Run "tuti debug errors" to see them.');
        }

        $this->newLine();
        $this->note('Available commands:');
        $this->line('  tuti debug enable    - Enable debug logging');
        $this->line('  tuti debug disable   - Disable debug logging');
        $this->line('  tuti debug logs      - View recent logs');
        $this->line('  tuti debug errors    - View errors only');
        $this->line('  tuti debug clear     - Clear log files');

        return self::SUCCESS;
    }

    private function enableDebug(DebugLogService $debugService): int
    {
        $this->brandedHeader('Enable Debug Mode');

        $debugService->enable();

        $this->success('Debug mode enabled');
        $this->note('All operations will now be logged to: ' . $debugService->getGlobalLogPath() . '/tuti.log');
        $this->hint('Run "tuti debug logs" to view logs');

        return self::SUCCESS;
    }

    private function disableDebug(DebugLogService $debugService): int
    {
        $this->brandedHeader('Disable Debug Mode');

        $debugService->disable();

        $this->success('Debug mode disabled');
        $this->note('Only errors will be logged from now on');

        return self::SUCCESS;
    }

    private function showLogs(DebugLogService $debugService): int
    {
        $this->brandedHeader('Debug Logs');

        $lines = (int) $this->option('lines');
        $level = $this->option('level');
        $sessionOnly = $this->option('session');

        if ($sessionOnly) {
            $logs = $debugService->getSessionLogs();
            $this->note('Showing current session logs');
        } else {
            // Read from file
            $logLines = $debugService->readLogFile(null, $lines);

            if (empty($logLines)) {
                $this->warning('No logs found');
                $this->hint('Run "tuti debug enable" to start logging');

                return self::SUCCESS;
            }

            $this->note("Last {$lines} log entries:");
            $this->newLine();

            foreach ($logLines as $line) {
                if (empty(mb_trim($line))) {
                    continue;
                }

                // Color-code by level
                if (str_contains($line, 'ERROR')) {
                    $this->line("<fg=red>{$line}</>");
                } elseif (str_contains($line, 'WARNING')) {
                    $this->line("<fg=yellow>{$line}</>");
                } elseif (str_contains($line, 'COMMAND') || str_contains($line, 'PROCESS')) {
                    $this->line("<fg=magenta>{$line}</>");
                } elseif (str_contains($line, 'INFO')) {
                    $this->line("<fg=green>{$line}</>");
                } else {
                    $this->line("<fg=cyan>{$line}</>");
                }
            }

            return self::SUCCESS;
        }

        // Filter by level if specified
        if ($level) {
            $logs = $debugService->getLogsByLevel($level);
        }

        if (empty($logs)) {
            $this->warning('No logs found for this session');

            return self::SUCCESS;
        }

        $this->line($debugService->formatLogsForDisplay($logs));

        return self::SUCCESS;
    }

    private function showErrors(DebugLogService $debugService): int
    {
        $this->brandedHeader('Error Logs');

        $errors = $debugService->getErrors();

        if (empty($errors)) {
            $this->success('No errors found in current session');

            return self::SUCCESS;
        }

        $this->warning(count($errors) . ' error(s) found:');
        $this->newLine();

        foreach ($errors as $error) {
            $this->line("<fg=red>[{$error['timestamp']}] [{$error['context']}]</>");
            $this->line("<fg=red>  {$error['message']}</>");

            if (! empty($error['data'])) {
                foreach ($error['data'] as $key => $value) {
                    if ($key === 'stderr' || $key === 'error') {
                        $this->line("<fg=yellow>  {$key}:</>");
                        // Show error output line by line
                        foreach (explode("\n", (string) $value) as $errorLine) {
                            if (! empty(mb_trim($errorLine))) {
                                $this->line("<fg=yellow>    {$errorLine}</>");
                            }
                        }
                    } else {
                        $this->line("<fg=gray>  {$key}: " . (is_string($value) ? $value : json_encode($value)) . '</>');
                    }
                }
            }
            $this->newLine();
        }

        $this->hint('Fix the errors above and try again');

        return self::SUCCESS;
    }

    private function clearLogs(DebugLogService $debugService): int
    {
        $this->brandedHeader('Clear Logs');

        $logPath = $debugService->getGlobalLogPath();
        $logFile = $logPath . '/tuti.log';

        // Clear session logs
        $debugService->clearSession();

        // Clear log file
        if (file_exists($logFile)) {
            unlink($logFile);
        }

        // Clear rotated logs
        for ($i = 1; $i <= 5; $i++) {
            $rotatedLog = $logFile . '.' . $i;
            if (file_exists($rotatedLog)) {
                unlink($rotatedLog);
            }
        }

        $this->success('All logs cleared');

        return self::SUCCESS;
    }

    private function showHelp(): int
    {
        $this->brandedHeader('Debug Help');

        $this->note('Usage: tuti debug <action>');
        $this->newLine();

        $this->line('Available actions:');
        $this->line('  status   - Show debug status (default)');
        $this->line('  enable   - Enable debug logging');
        $this->line('  disable  - Disable debug logging');
        $this->line('  logs     - View recent logs');
        $this->line('  errors   - View errors only');
        $this->line('  clear    - Clear all logs');
        $this->newLine();

        $this->line('Options:');
        $this->line('  --lines=N   - Number of log lines to show (default: 50)');
        $this->line('  --level=X   - Filter by level (error, warning, info, debug)');
        $this->line('  --session   - Show only current session logs');

        return self::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
