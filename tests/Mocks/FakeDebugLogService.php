<?php

declare(strict_types=1);

namespace Tests\Mocks;

use App\Services\Debug\DebugLogService;

/**
 * Class FakeDebugLogService
 *
 * Fake implementation of DebugLogService for testing.
 * Allows controlling behavior and tracking method calls.
 */
final class FakeDebugLogService
{
    public bool $enabled = false;

    public string $globalLogPath = '/fake/.tuti/logs/tuti.log';

    public array $errors = [];

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getGlobalLogPath(): string
    {
        return $this->globalLogPath;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Set debug mode as enabled.
     */
    public function setEnabled(bool $enabled = true): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Set errors that should be returned.
     *
     * @param  array<string>  $errors
     */
    public function setErrors(array $errors): void
    {
        $this->errors = $errors;
    }

    /**
     * Add an error to the error list.
     */
    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    /**
     * Reset all tracking data to initial state.
     */
    public function reset(): void
    {
        $this->enabled = false;
        $this->globalLogPath = '/fake/.tuti/logs/tuti.log';
        $this->errors = [];
    }
}
