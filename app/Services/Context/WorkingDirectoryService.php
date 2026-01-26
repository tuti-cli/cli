<?php

declare(strict_types=1);

namespace App\Services\Context;

use RuntimeException;

/**
 * Service WorkingDirectoryService
 *
 * Manages the current working directory context - where the user runs tuti commands.
 * This is NOT where tuti CLI is installed, but where their project lives.
 */
final class WorkingDirectoryService
{
    private ?string $workingDirectory = null;

    /**
     * Get the current working directory (where user runs the command).
     */
    public function getWorkingDirectory(): string
    {
        if ($this->workingDirectory !== null) {
            return $this->workingDirectory;
        }

        $cwd = getcwd();

        if ($cwd === false) {
            throw new RuntimeException('Unable to determine current working directory.');
        }

        return $this->workingDirectory = $cwd;
    }

    /**
     * Set working directory explicitly (useful for testing).
     */
    public function setWorkingDirectory(string $path): void
    {
        if (! is_dir($path)) {
            throw new RuntimeException("Directory does not exist: {$path}");
        }

        $this->workingDirectory = realpath($path) ?: $path;
    }

    /**
     * Get the .tuti directory path within the working directory.
     */
    public function getTutiPath(?string $subPath = null): string
    {
        $base = $this->getWorkingDirectory() . '/.tuti';

        if ($subPath === null) {
            return $base;
        }

        return $base . '/' . ltrim($subPath, '/');
    }

    /**
     * Check if .tuti directory exists.
     */
    public function tutiExists(): bool
    {
        return is_dir($this->getTutiPath());
    }

    /**
     * Get a path relative to the working directory.
     */
    public function getPath(string $relativePath): string
    {
        return $this->getWorkingDirectory() . '/' . ltrim($relativePath, '/');
    }

    /**
     * Reset the cached working directory (useful for tests).
     */
    public function reset(): void
    {
        $this->workingDirectory = null;
    }
}
