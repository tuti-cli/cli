<?php

declare(strict_types=1);

namespace App\Services\Project;

use RuntimeException;

/**
 * Service ProjectDirectoryService
 *
 * Responsible for handling project file structure and paths.
 * It strictly adheres to the convention that a project is defined by a
 * `.tuti` directory or `tuti.json` file.
 */
final readonly class ProjectDirectoryService
{
    /**
     * Get the root directory of the current project.
     */
    public function getProjectRoot(): string
    {
        $current = getcwd();

        while ($current !== '/' && $current !== 'C:\\') {
            if (file_exists($current . '/tuti.json') || is_dir($current . '/.tuti')) {
                return $current;
            }

            $current = dirname($current);
        }

        throw new RuntimeException("Not in a Tuti project. Run 'tuti init' first.");
    }

    /**
     * Get the path to the .tuti directory
     */
    public function getTutiDir(): string
    {
        return tuti_path(null, $this->getProjectRoot());
    }

    /**
     * Get path to a file inside .tuti directory
     */
    public function getTutiPath(string $path = ''): string
    {
        return tuti_path($path, $this->getProjectRoot());
    }

    /**
     * Check if .tuti directory exists
     */
    public function exists(): bool
    {
        try {
            return is_dir($this->getTutiDir());
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Initialize .tuti directory structure
     */
    public function initialize(): bool
    {
        if ($this->exists()) {
            throw new RuntimeException('Project already initialized. .tuti directory already exists.');
        }

        $this->createBaseStructure();

        return true;
    }

    /**
     * Get all directories that should exist in .tuti
     */
    public function getRequiredDirectories(): array
    {
        return [
            'docker',
            'environments',
            'scripts',
        ];
    }

    /**
     * Validate .tuti directory structure
     */
    public function validate(): bool
    {
        if (!$this->exists()) {
            return false;
        }

        foreach ($this->getRequiredDirectories() as $dir) {
            if (!is_dir($this->getTutiPath($dir))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create base .tuti directory structure
     */
    private function createBaseStructure(): void
    {
        $directories = array_merge([''], $this->getRequiredDirectories());

        foreach ($directories as $dir) {
            $path = $this->getTutiPath($dir);

            if (!is_dir($path) && !mkdir($path, 0755, true)) {
                throw new RuntimeException("Failed to create directory: {$path}");
            }
        }
    }
}
