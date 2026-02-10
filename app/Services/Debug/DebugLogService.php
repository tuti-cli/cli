<?php

declare(strict_types=1);

namespace App\Services\Debug;

use Illuminate\Support\Facades\File;

/**
 * Debug Log Service.
 *
 * Provides a simple debug logging system for tuti-cli.
 * Logs are stored in ~/.tuti/logs/ and project-specific logs in .tuti/logs/
 */
final class DebugLogService
{
    private const int MAX_LOG_SIZE = 5 * 1024 * 1024; // 5MB

    private const int MAX_LOG_FILES = 5;

    private static ?self $instance = null;

    /** @var array<int, array{timestamp: string, level: string, context: string, message: string, data: array<string, mixed>}> */
    private array $sessionLogs = [];

    private bool $debugEnabled = false;

    private ?string $currentContext = null;

    public function __construct()
    {
        $this->debugEnabled = (bool) getenv('TUTI_DEBUG') || file_exists($this->getGlobalLogPath() . '/.debug-enabled');
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Enable debug mode.
     */
    public function enable(): void
    {
        $this->debugEnabled = true;

        $debugFlagFile = $this->getGlobalLogPath() . '/.debug-enabled';
        $logDir = dirname($debugFlagFile);

        if (! is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents($debugFlagFile, date('c'));
    }

    /**
     * Disable debug mode.
     */
    public function disable(): void
    {
        $this->debugEnabled = false;

        $debugFlagFile = $this->getGlobalLogPath() . '/.debug-enabled';
        if (file_exists($debugFlagFile)) {
            unlink($debugFlagFile);
        }
    }

    /**
     * Check if debug mode is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->debugEnabled;
    }

    /**
     * Set the current context (e.g., "docker:start", "stack:init").
     */
    public function setContext(string $context): void
    {
        $this->currentContext = $context;
    }

    /**
     * Log a debug message.
     *
     * @param  array<string, mixed>  $data
     */
    public function debug(string $message, array $data = []): void
    {
        $this->log('DEBUG', $message, $data);
    }

    /**
     * Log an info message.
     *
     * @param  array<string, mixed>  $data
     */
    public function info(string $message, array $data = []): void
    {
        $this->log('INFO', $message, $data);
    }

    /**
     * Log a warning message.
     *
     * @param  array<string, mixed>  $data
     */
    public function warning(string $message, array $data = []): void
    {
        $this->log('WARNING', $message, $data);
    }

    /**
     * Log an error message.
     *
     * @param  array<string, mixed>  $data
     */
    public function error(string $message, array $data = []): void
    {
        $this->log('ERROR', $message, $data);
    }

    /**
     * Log a command execution.
     *
     * @param  array<string, mixed>  $result
     */
    public function command(string $command, array $result = []): void
    {
        $this->log('COMMAND', $command, $result);
    }

    /**
     * Log process output (stdout/stderr).
     */
    public function processOutput(string $command, string $stdout, string $stderr, int $exitCode): void
    {
        $this->log('PROCESS', $command, [
            'exit_code' => $exitCode,
            'stdout' => $this->truncateOutput($stdout),
            'stderr' => $this->truncateOutput($stderr),
        ]);
    }

    /**
     * Get session logs.
     *
     * @return array<int, array{timestamp: string, level: string, context: string, message: string, data: array<string, mixed>}>
     */
    public function getSessionLogs(): array
    {
        return $this->sessionLogs;
    }

    /**
     * Get the last N log entries.
     *
     * @return array<int, array{timestamp: string, level: string, context: string, message: string, data: array<string, mixed>}>
     */
    public function getLastLogs(int $count = 50): array
    {
        return array_slice($this->sessionLogs, -$count);
    }

    /**
     * Get logs filtered by level.
     *
     * @return array<int, array{timestamp: string, level: string, context: string, message: string, data: array<string, mixed>}>
     */
    public function getLogsByLevel(string $level): array
    {
        return array_filter($this->sessionLogs, fn ($log) => $log['level'] === mb_strtoupper($level));
    }

    /**
     * Get error logs only.
     *
     * @return array<int, array{timestamp: string, level: string, context: string, message: string, data: array<string, mixed>}>
     */
    public function getErrors(): array
    {
        return array_filter($this->sessionLogs, fn ($log) => in_array($log['level'], ['ERROR', 'WARNING']));
    }

    /**
     * Clear session logs.
     */
    public function clearSession(): void
    {
        $this->sessionLogs = [];
    }

    /**
     * Read logs from file.
     *
     * @return array<int, string>
     */
    public function readLogFile(?string $path = null, int $lines = 100): array
    {
        $logFile = $path ?? $this->getGlobalLogPath() . '/tuti.log';

        if (! file_exists($logFile)) {
            return [];
        }

        $content = file_get_contents($logFile);
        $allLines = explode("\n", $content);

        return array_slice($allLines, -$lines);
    }

    /**
     * Get the path to global logs directory.
     */
    public function getGlobalLogPath(): string
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE') ?: '/tmp';

        return mb_rtrim($home, '/\\') . DIRECTORY_SEPARATOR . '.tuti' . DIRECTORY_SEPARATOR . 'logs';
    }

    /**
     * Get the path to project logs directory.
     */
    public function getProjectLogPath(): string
    {
        $cwd = getcwd() ?: '.';

        return $cwd . '/.tuti/logs';
    }

    /**
     * Format logs for display.
     *
     * @param  array<int, array{timestamp: string, level: string, context: string, message: string, data: array<string, mixed>}>  $logs
     */
    public function formatLogsForDisplay(array $logs): string
    {
        $output = '';

        foreach ($logs as $log) {
            $levelColor = match ($log['level']) {
                'ERROR' => "\033[31m",   // Red
                'WARNING' => "\033[33m", // Yellow
                'INFO' => "\033[32m",    // Green
                'DEBUG' => "\033[36m",   // Cyan
                'COMMAND' => "\033[35m", // Magenta
                'PROCESS' => "\033[34m", // Blue
                default => "\033[0m",    // Reset
            };

            $output .= sprintf(
                "%s[%s] %s%s\033[0m [%s] %s\n",
                $levelColor,
                mb_substr($log['timestamp'], 11, 8), // Just time
                mb_str_pad($log['level'], 7),
                "\033[0m",
                $log['context'],
                $log['message']
            );

            if (! empty($log['data'])) {
                foreach ($log['data'] as $key => $value) {
                    if (is_string($value) && mb_strlen($value) > 100) {
                        $value = mb_substr($value, 0, 100) . '...';
                    }
                    $output .= sprintf("         %s: %s\n", $key, is_string($value) ? $value : json_encode($value));
                }
            }
        }

        return $output;
    }

    /**
     * Internal log method.
     *
     * @param  array<string, mixed>  $data
     */
    private function log(string $level, string $message, array $data = []): void
    {
        $entry = [
            'timestamp' => date('Y-m-d H:i:s.u'),
            'level' => $level,
            'context' => $this->currentContext ?? 'general',
            'message' => $message,
            'data' => $data,
        ];

        // Always store in session
        $this->sessionLogs[] = $entry;

        // Write to file if debug enabled or it's an error
        if ($this->debugEnabled || $level === 'ERROR') {
            $this->writeToFile($entry);
        }
    }

    /**
     * Write log entry to file.
     *
     * @param  array{timestamp: string, level: string, context: string, message: string, data: array<string, mixed>}  $entry
     */
    private function writeToFile(array $entry): void
    {
        $logDir = $this->getGlobalLogPath();

        if (! is_dir($logDir)) {
            if (! @mkdir($logDir, 0755, true) && ! is_dir($logDir)) {
                return; // Silently fail if can't create log dir
            }
        }

        $logFile = $logDir . '/tuti.log';

        // Rotate logs if needed
        $this->rotateLogsIfNeeded($logFile);

        // Format log line
        $line = sprintf(
            "[%s] %s [%s] %s%s\n",
            $entry['timestamp'],
            mb_str_pad($entry['level'], 7),
            $entry['context'],
            $entry['message'],
            ! empty($entry['data']) ? ' ' . json_encode($entry['data'], JSON_UNESCAPED_SLASHES) : ''
        );

        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Rotate log files if the main log is too large.
     */
    private function rotateLogsIfNeeded(string $logFile): void
    {
        if (! file_exists($logFile) || filesize($logFile) < self::MAX_LOG_SIZE) {
            return;
        }

        // Rotate existing log files
        for ($i = self::MAX_LOG_FILES - 1; $i >= 1; $i--) {
            $oldFile = $logFile . '.' . $i;
            $newFile = $logFile . '.' . ($i + 1);

            if (file_exists($oldFile)) {
                if ($i === self::MAX_LOG_FILES - 1) {
                    unlink($oldFile);
                } else {
                    rename($oldFile, $newFile);
                }
            }
        }

        // Rotate current log
        rename($logFile, $logFile . '.1');
    }

    /**
     * Truncate long output for logging.
     */
    private function truncateOutput(string $output, int $maxLength = 2000): string
    {
        $output = mb_trim($output);

        if (mb_strlen($output) <= $maxLength) {
            return $output;
        }

        return mb_substr($output, 0, $maxLength) . "\n... [truncated, " . mb_strlen($output) . ' total bytes]';
    }
}
